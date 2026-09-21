<?php

namespace Tests\Feature\Analytics;

use App\Enums\DriverExpenseStatus;
use App\Enums\DriverExpenseType;
use App\Enums\ExpenseStatus;
use App\Models\City;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverExpense;
use App\Models\TransferRequest;
use App\Models\User;
use App\Services\Analytics\TransferAnalytics as A;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransferAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const RATE = 12200;

    private User $operator;

    private Company $company;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::create(['from' => 'UZS', 'to' => 'USD', 'rate' => self::RATE, 'is_main' => true]);
        $this->operator = User::factory()->create(['name' => 'Olga Operator', 'role' => 1]);
        $this->company = Company::query()->forceCreate(['name' => 'Acme Travel']);
        $this->city = City::factory()->create(['name' => 'Samarkand']);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** A priced, confirmed, manual transfer on the given day. */
    private function transfer(string $when, array $attrs = []): Transfer
    {
        $t = Transfer::factory()->create(array_merge([
            'date_time' => Carbon::parse($when),
            'status' => ExpenseStatus::Confirmed,
            'company_id' => $this->company->id,
            'to_city_id' => $this->city->id,
            'pax' => 2,
            'driver_ids' => [],
        ], $attrs));

        // Transfer::creating() stamps the ambient user; set the operator explicitly.
        $t->forceFill(['created_by' => $this->operator->id])->saveQuietly();

        return $t;
    }

    /** 100 USD sold (stored at a real conversion), 40 USD bought. */
    private function priced(string $when, array $attrs = []): Transfer
    {
        return $this->transfer($when, array_merge([
            'sell_price' => 100, 'sell_price_currency' => 'USD', 'sell_price_result' => 1_220_000,
            'buy_price' => 40, 'buy_price_currency' => 'USD', 'buy_price_result' => 488_000,
        ], $attrs));
    }

    private function expense(Transfer $t, int $amount, DriverExpenseStatus $status = DriverExpenseStatus::Approved, ?Driver $by = null, DriverExpenseType $type = DriverExpenseType::Parking): TransferDriverExpense
    {
        return TransferDriverExpense::factory()->create([
            'transfer_id' => $t->id,
            'driver_id' => ($by ?? Driver::factory()->create(['name' => 'Rustam']))->id,
            'amount' => $amount,
            'status' => $status,
            'type' => $type,
        ]);
    }

    private function tourTransfer(int $tourType, array $attrs = []): Transfer
    {
        $tourId = DB::table('tours')->insertGetId([
            'group_number' => 'G'.uniqid(), 'company_id' => $this->company->id, 'created_by' => $this->operator->id, 'type' => $tourType,
        ]);
        $dayId = DB::table('tour_days')->insertGetId(['date' => '2026-05-10', 'city_id' => $this->city->id, 'tour_id' => $tourId]);
        $groupId = $tourType === 2 ? DB::table('tour_groups')->insertGetId(['tour_id' => $tourId]) : null;

        $expenseId = DB::table('tour_day_expenses')->insertGetId([
            'type' => 3, 'tour_id' => $tourId, 'tour_day_id' => $tourType === 2 ? null : $dayId, 'tour_group_id' => $groupId,
        ]);

        return $this->priced('2026-05-10 10:00', array_merge(['tour_day_expense_id' => $expenseId], $attrs));
    }

    private function report(string $from = '2026-05-01', string $to = '2026-05-31', ?array $sources = null): array
    {
        return A::make()->build(Carbon::parse($from), Carbon::parse($to), $sources);
    }

    // ── what is counted ───────────────────────────────────────────────────

    public function test_only_confirmed_and_done_transfers_are_counted(): void
    {
        $this->priced('2026-05-10 10:00', ['status' => ExpenseStatus::Confirmed]);
        $this->priced('2026-05-11 10:00', ['status' => ExpenseStatus::Done]);
        $this->priced('2026-05-12 10:00', ['status' => ExpenseStatus::New]);
        $this->priced('2026-05-13 10:00', ['status' => ExpenseStatus::Rejected]);

        $s = $this->report()['summary'];

        $this->assertSame(2, $s['trips']);
        $this->assertSame(1, $s['done']);
        $this->assertSame(1, $s['confirmed']);
        $this->assertSame(2_440_000.0, $s['revenue_uzs'], 'a New draft and a Rejected trip earn nothing');
    }

    public function test_the_period_is_by_the_transfers_own_date_and_inclusive_at_both_ends(): void
    {
        $this->priced('2026-04-30 23:59:59');          // the day before: out
        $this->priced('2026-05-01 00:00:00');          // first second: in
        $this->priced('2026-05-31 23:59:59');          // last second: in
        $this->priced('2026-06-01 00:00:00');          // the day after: out

        $r = $this->report('2026-05-01', '2026-05-31');

        $this->assertSame(2, $r['summary']['trips']);
        $this->assertSame(['2026-05-01', '2026-05-31'], array_column($r['rows'], 'day'));
    }

    // ── the arithmetic ────────────────────────────────────────────────────

    public function test_revenue_supplier_cost_driver_expenses_and_profit_add_up(): void
    {
        $t = $this->priced('2026-05-10 10:00');
        $this->expense($t, 50_000);

        $s = $this->report()['summary'];

        $this->assertSame(1_220_000.0, $s['revenue_uzs']);
        $this->assertSame(488_000.0, $s['supplier_uzs']);
        $this->assertSame(50_000.0, $s['driver_exp_uzs']);
        $this->assertSame(682_000.0, $s['profit_uzs']);
        $this->assertSame(round(682_000 / 1_220_000, 4), $s['margin']);
        $this->assertSame(538_000.0, $s['expenses_uzs'], 'all expenses = supplier + driver');

        // USD side: 100 sold, 40 bought, 50 000 sums of expenses at today's rate.
        $expUsd = round(50_000 / self::RATE, 2);
        $this->assertSame(100.0, $s['revenue_usd']);
        $this->assertSame(40.0, $s['supplier_usd']);
        $this->assertSame($expUsd, $s['driver_exp_usd']);
        $this->assertSame(round(100 - 40 - $expUsd, 2), $s['profit_usd']);
    }

    public function test_the_stored_historical_conversion_is_kept_not_repriced_at_todays_rate(): void
    {
        // Entered when the rate was 11 800: 25 USD -> 295 000, not 25 x 12 200 = 305 000.
        $this->transfer('2026-05-10 10:00', ['sell_price' => 25, 'sell_price_currency' => 'USD', 'sell_price_result' => 295_000]);

        $this->assertSame(295_000.0, $this->report()['summary']['revenue_uzs']);
    }

    public function test_a_transfer_with_no_currency_and_a_small_price_counts_as_dollars_and_is_flagged(): void
    {
        // The real-data pattern: price 220, NO currency, result == price. Counted as sums it would be 220 UZS.
        $this->transfer('2026-05-10 10:00', ['sell_price' => 220, 'sell_price_currency' => null, 'sell_price_result' => 220]);

        $r = $this->report();

        $this->assertSame(220.0, $r['summary']['revenue_usd']);
        $this->assertSame(220 * 12200.0, $r['summary']['revenue_uzs']);
        $this->assertContains('currency_assumed', array_column($r['issues'], 'code'));
    }

    public function test_a_transfer_with_no_currency_and_a_large_price_counts_as_sums(): void
    {
        $this->transfer('2026-05-10 10:00', ['sell_price' => 2_300_000, 'sell_price_currency' => null, 'sell_price_result' => 2_300_000]);

        $this->assertSame(2_300_000.0, $this->report()['summary']['revenue_uzs']);
    }

    // ── sources ───────────────────────────────────────────────────────────

    public function test_website_manual_and_corporate_transfers_are_revenue_but_package_tour_transfers_are_not(): void
    {
        $request = TransferRequest::create(['date_time' => Carbon::parse('2026-05-10'), 'passengers_count' => 1, 'from' => 'A', 'to' => 'B']);
        $this->priced('2026-05-10 10:00', ['transfer_request_id' => $request->id]);   // website
        $this->priced('2026-05-10 11:00');                                            // manual
        $this->tourTransfer(2);                                                       // corporate tour
        $this->tourTransfer(1);                                                       // package (TPS) tour

        $r = $this->report();
        $by = $r['by_source'];

        $this->assertSame(1, $by[A::SOURCE_WEBSITE]['trips']);
        $this->assertSame(1, $by[A::SOURCE_MANUAL]['trips']);
        $this->assertSame(1, $by[A::SOURCE_CORPORATE]['trips']);
        $this->assertSame(1, $by[A::SOURCE_PACKAGE]['trips']);

        foreach ([A::SOURCE_WEBSITE, A::SOURCE_MANUAL, A::SOURCE_CORPORATE] as $revenueSource) {
            $this->assertSame(1_220_000.0, $by[$revenueSource]['revenue_uzs'], $revenueSource);
        }
        $this->assertSame(0.0, $by[A::SOURCE_PACKAGE]['revenue_uzs'], 'a package transfer\'s price is a copy of a tour COST, not income');
        $this->assertSame(1_220_000.0, $by[A::SOURCE_PACKAGE]['package_memo_uzs'], 'kept as a memo only');

        $this->assertSame(3 * 1_220_000.0, $r['summary']['revenue_uzs'], 'revenue excludes the package transfer');
    }

    public function test_package_tour_costs_are_still_reported_but_never_netted_against_revenue(): void
    {
        $package = $this->tourTransfer(1);
        $this->expense($package, 30_000);

        $s = $this->report()['summary'];

        $this->assertSame(0.0, $s['revenue_uzs']);
        $this->assertSame(0.0, $s['profit_uzs'], 'no revenue was counted, so no (negative) profit is invented');
        $this->assertSame(488_000.0 + 30_000, $s['package_cost_uzs']);
        $this->assertSame(488_000.0 + 30_000, $s['expenses_uzs'], 'but it is inside "all expenses"');
        $this->assertSame(0.0, $s['supplier_uzs']);
    }

    public function test_a_tour_linked_transfer_whose_tour_cannot_be_resolved_is_not_counted_as_revenue(): void
    {
        $expenseId = DB::table('tour_day_expenses')->insertGetId(['type' => 3]);   // linked to no tour at all
        $this->priced('2026-05-10 10:00', ['tour_day_expense_id' => $expenseId]);

        $r = $this->report();

        $this->assertSame(A::SOURCE_PACKAGE, $r['rows'][0]['source'], 'the safe side: never overstate income');
        $this->assertSame(0.0, $r['summary']['revenue_uzs']);
    }

    public function test_the_source_filter_restricts_everything(): void
    {
        $this->priced('2026-05-10 10:00');
        $this->tourTransfer(2);

        $r = $this->report(sources: [A::SOURCE_MANUAL]);

        $this->assertSame(1, $r['summary']['trips']);
        $this->assertSame([A::SOURCE_MANUAL], array_keys($r['by_source']));
    }

    // ── driver expenses ───────────────────────────────────────────────────

    public function test_rejected_driver_expenses_are_excluded_and_pending_ones_are_counted_but_split_out(): void
    {
        $t = $this->priced('2026-05-10 10:00');
        $this->expense($t, 10_000, DriverExpenseStatus::Approved);
        $this->expense($t, 20_000, DriverExpenseStatus::New);
        $this->expense($t, 999_000, DriverExpenseStatus::Rejected);

        $s = $this->report()['summary'];

        $this->assertSame(30_000.0, $s['driver_exp_uzs']);
        $this->assertSame(10_000.0, $s['driver_exp_approved_uzs']);
        $this->assertSame(20_000.0, $s['driver_exp_pending_all_uzs']);
    }

    public function test_driver_expenses_belong_to_the_transfers_date_not_the_day_they_were_typed(): void
    {
        $inMay = $this->priced('2026-05-31 20:00');
        $inJune = $this->priced('2026-06-01 09:00');
        $this->expense($inMay, 10_000);
        $this->expense($inJune, 77_000);

        $this->assertSame(10_000.0, $this->report('2026-05-01', '2026-05-31')['summary']['driver_exp_uzs']);
    }

    public function test_expense_types_are_totalled_for_the_expenses_sheet(): void
    {
        $t = $this->priced('2026-05-10 10:00');
        $this->expense($t, 10_000, type: DriverExpenseType::Fuel);
        $this->expense($t, 5_000, type: DriverExpenseType::Fuel);
        $this->expense($t, 7_000, type: DriverExpenseType::Parking);

        $types = $this->report()['expense_types'];

        $this->assertSame(['count' => 2, 'uzs' => 15_000.0, 'usd' => round(15_000 / self::RATE, 2)], $types[DriverExpenseType::Fuel->getLabel()]);
        $this->assertSame(7_000.0, $types[DriverExpenseType::Parking->getLabel()]['uzs']);
    }

    public function test_per_driver_figures_count_trips_assigned_and_expenses_entered(): void
    {
        $rustam = Driver::factory()->create(['name' => 'Rustam']);
        $aziz = Driver::factory()->create(['name' => 'Aziz']);
        $shared = $this->priced('2026-05-10 10:00', ['driver_ids' => [(string) $rustam->id, (string) $aziz->id]]);
        $this->priced('2026-05-11 10:00', ['driver_ids' => [(string) $rustam->id]]);
        $this->expense($shared, 40_000, by: $aziz);

        $d = $this->report()['by_driver'];

        $this->assertSame(2, $d['Rustam']['trips']);
        $this->assertSame(1, $d['Aziz']['trips'], 'a shared trip counts for each driver');
        $this->assertSame(40_000.0, $d['Aziz']['exp_uzs'], 'expenses are attributed to whoever entered them');
        $this->assertSame(0.0, $d['Rustam']['exp_uzs']);
    }

    public function test_a_shared_trips_supplier_cost_is_not_split_or_doubled_between_its_drivers(): void
    {
        $a = Driver::factory()->create();
        $b = Driver::factory()->create();
        $this->priced('2026-05-10 10:00', ['driver_ids' => [(string) $a->id, (string) $b->id]]);

        $this->assertSame(488_000.0, $this->report()['summary']['supplier_uzs']);
    }

    // ── time series ───────────────────────────────────────────────────────

    public function test_the_daily_series_has_every_day_including_empty_ones(): void
    {
        $this->priced('2026-05-03 10:00');

        $days = $this->report('2026-05-01', '2026-05-05')['by_day'];

        $this->assertSame(['2026-05-01', '2026-05-02', '2026-05-03', '2026-05-04', '2026-05-05'], array_keys($days));
        $this->assertSame(0, $days['2026-05-02']['trips']);
        $this->assertSame(1, $days['2026-05-03']['trips']);
        $this->assertNull($days['2026-05-02']['margin'], 'no revenue means no margin, not 0%');
    }

    public function test_the_monthly_series_spans_month_boundaries(): void
    {
        $this->priced('2026-04-20 10:00');
        $this->priced('2026-05-05 10:00');
        $this->priced('2026-05-06 10:00');

        $months = $this->report('2026-04-15', '2026-06-10')['by_month'];

        $this->assertSame(['2026-04', '2026-05', '2026-06'], array_keys($months));
        $this->assertSame([1, 2, 0], array_column($months, 'trips'));
    }

    public function test_daily_monthly_source_and_summary_totals_all_agree(): void
    {
        $request = TransferRequest::create(['date_time' => Carbon::parse('2026-05-10'), 'passengers_count' => 1, 'from' => 'A', 'to' => 'B']);
        $t1 = $this->priced('2026-04-28 10:00');
        $t2 = $this->priced('2026-05-10 10:00', ['transfer_request_id' => $request->id]);
        $t3 = $this->tourTransfer(2);
        $this->tourTransfer(1);
        $this->transfer('2026-05-20 10:00', ['sell_price' => 220, 'sell_price_currency' => null, 'sell_price_result' => 220]);
        $this->expense($t1, 11_000);
        $this->expense($t2, 22_000, DriverExpenseStatus::New);
        $this->expense($t3, 33_000);

        $r = $this->report('2026-04-01', '2026-05-31');

        foreach (['trips', 'revenue_uzs', 'revenue_usd', 'supplier_uzs', 'driver_exp_uzs', 'profit_uzs', 'package_cost_uzs', 'expenses_uzs', 'unpriced'] as $field) {
            $expected = $r['summary'][$field];
            foreach (['by_day', 'by_month', 'by_source'] as $view) {
                $this->assertEqualsWithDelta($expected, array_sum(array_column($r[$view], $field)), 0.01, "{$field} via {$view}");
            }
        }
    }

    // ── grouping ──────────────────────────────────────────────────────────

    public function test_clients_operators_and_cities_are_grouped_biggest_revenue_first_with_a_placeholder_for_blanks(): void
    {
        $other = Company::query()->forceCreate(['name' => 'Big Client']);
        $this->priced('2026-05-10 10:00');
        $this->priced('2026-05-11 10:00', ['company_id' => $other->id, 'sell_price' => 500, 'sell_price_result' => 6_100_000]);
        $this->priced('2026-05-12 10:00', ['company_id' => null]);

        $r = $this->report();

        $this->assertSame(['Big Client', 'Acme Travel', '—'], array_keys($r['by_company']));
        $this->assertSame(['Olga Operator'], array_keys($r['by_operator']));
        $this->assertSame(['Samarkand'], array_keys($r['by_city']));
    }

    // ── data quality ──────────────────────────────────────────────────────

    public function test_transfers_without_a_sell_price_are_counted_but_flagged_as_unpriced(): void
    {
        $this->transfer('2026-05-10 10:00');   // no prices at all

        $r = $this->report();

        $this->assertSame(1, $r['summary']['trips']);
        $this->assertSame(1, $r['summary']['unpriced']);
        $this->assertContains('no_sell', array_column($r['issues'], 'code'));
        $this->assertContains('no_buy', array_column($r['issues'], 'code'));
    }

    public function test_a_loss_making_transfer_is_flagged(): void
    {
        $this->transfer('2026-05-10 10:00', [
            'sell_price' => 20, 'sell_price_currency' => 'USD', 'sell_price_result' => 244_000,
            'buy_price' => 40, 'buy_price_currency' => 'USD', 'buy_price_result' => 488_000,
        ]);

        $issue = collect($this->report()['issues'])->firstWhere('code', 'loss');

        $this->assertNotNull($issue);
        $this->assertSame('-244000', $issue['detail']);
    }

    public function test_pending_driver_expenses_are_flagged_for_review(): void
    {
        $t = $this->priced('2026-05-10 10:00');
        $this->expense($t, 20_000, DriverExpenseStatus::New);

        $this->assertContains('expenses_pending', array_column($this->report()['issues'], 'code'));
    }

    public function test_a_clean_transfer_raises_no_issue(): void
    {
        $this->priced('2026-05-10 10:00');

        $this->assertSame([], $this->report()['issues']);
    }

    public function test_package_transfers_are_not_flagged_for_lacking_a_sell_price(): void
    {
        $t = $this->tourTransfer(1);
        $t->forceFill(['sell_price' => null, 'sell_price_result' => null])->saveQuietly();

        $this->assertNotContains('no_sell', array_column($this->report()['issues'], 'code'));
    }

    // ── robustness ────────────────────────────────────────────────────────

    public function test_an_empty_period_is_a_valid_report_of_zeros(): void
    {
        $r = $this->report();

        $this->assertSame(0, $r['summary']['trips']);
        $this->assertSame(0.0, $r['summary']['revenue_uzs']);
        $this->assertNull($r['summary']['margin']);
        $this->assertNull($r['summary']['avg_revenue_per_trip_uzs']);
        $this->assertSame([], $r['rows']);
        $this->assertSame([], $r['issues']);
    }

    public function test_reversed_dates_are_swapped_not_rejected(): void
    {
        $this->priced('2026-05-10 10:00');

        $this->assertSame(1, $this->report('2026-05-31', '2026-05-01')['summary']['trips']);
    }

    public function test_a_period_longer_than_the_limit_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->report('2020-01-01', '2026-05-31');
    }

    public function test_without_a_configured_rate_nothing_crashes_and_the_gap_is_flagged(): void
    {
        Currency::query()->delete();
        $this->transfer('2026-05-10 10:00', ['sell_price' => 100, 'sell_price_currency' => 'USD', 'sell_price_result' => 0]);

        $r = $this->report();

        $this->assertSame(0.0, $r['usd_rate']);
        $this->assertSame(100.0, $r['summary']['revenue_usd'], 'the USD amount is still known');
        $this->assertContains('no_rate', array_column($r['issues'], 'code'));
    }

    public function test_the_average_revenue_per_trip_ignores_package_transfers_and_unpriced_ones(): void
    {
        $this->priced('2026-05-10 10:00');
        $this->priced('2026-05-11 10:00', ['sell_price' => 300, 'sell_price_result' => 3_660_000]);
        $this->tourTransfer(1);
        $this->transfer('2026-05-12 10:00');   // no price: must not drag the average down

        // (1 220 000 + 3 660 000) / 2 priced trips
        $this->assertSame(2_440_000.0, $this->report()['summary']['avg_revenue_per_trip_uzs']);
    }

    public function test_price_coverage_says_how_much_of_the_total_can_be_believed(): void
    {
        $this->priced('2026-05-10 10:00');                                         // priced + bought
        $this->transfer('2026-05-11 10:00', ['sell_price' => 100, 'sell_price_currency' => 'USD', 'sell_price_result' => 1_220_000]);   // priced, no buy
        $this->transfer('2026-05-12 10:00');                                       // nothing
        $this->transfer('2026-05-13 10:00');                                       // nothing
        $this->tourTransfer(1);                                                    // package: outside the coverage figure

        $s = $this->report()['summary'];

        $this->assertSame(4, $s['revenue_trips'], 'the package transfer is not expected to earn money');
        $this->assertSame(2, $s['unpriced']);
        $this->assertSame(0.5, $s['priced_share'], '2 of 4 have a sell price');
        $this->assertSame(0.25, $s['bought_share'], 'only 1 of 4 has a buy price');
    }

    public function test_coverage_is_null_not_100_percent_when_nothing_should_earn(): void
    {
        $this->tourTransfer(1);

        $this->assertNull($this->report()['summary']['priced_share']);
    }
}

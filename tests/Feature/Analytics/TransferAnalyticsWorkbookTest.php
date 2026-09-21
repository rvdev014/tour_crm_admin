<?php

namespace Tests\Feature\Analytics;

use App\Enums\DriverExpenseStatus;
use App\Enums\ExpenseStatus;
use App\Models\City;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverExpense;
use App\Models\User;
use App\Services\Analytics\TransferAnalytics;
use App\Services\Analytics\TransferAnalyticsWorkbook as W;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Builds a real .xlsx from real DB rows, re-opens it, RECALCULATES every formula and compares with the
 * service — so a formula that points at the wrong column, a bad sheet reference or a #NAME? error is caught.
 */
class TransferAnalyticsWorkbookTest extends TestCase
{
    use RefreshDatabase;

    private const RATE = 12200;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ru');
        $this->path = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        Currency::create(['from' => 'UZS', 'to' => 'USD', 'rate' => self::RATE, 'is_main' => true]);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    // ── fixtures ──────────────────────────────────────────────────────────

    private function operator(): User
    {
        return User::query()->firstOrCreate(['email' => 'op@example.com'], ['name' => 'Olga Operator', 'password' => 'x', 'role' => 1]);
    }

    private function company(): Company
    {
        return Company::query()->firstOrCreate(['name' => 'Acme Travel']);
    }

    private function city(): City
    {
        return City::query()->firstOrCreate(
            ['name' => 'Samarkand'],
            ['country_id' => \App\Models\Country::firstOrCreate(['name' => 'Uzbekistan'])->id],
        );
    }

    private function transfer(string $when, array $attrs = []): Transfer
    {
        $t = Transfer::factory()->create(array_merge([
            'date_time' => Carbon::parse($when), 'status' => ExpenseStatus::Confirmed, 'pax' => 2, 'driver_ids' => [],
            'company_id' => $this->company()->id,
            'to_city_id' => $this->city()->id,
            'sell_price' => 100, 'sell_price_currency' => 'USD', 'sell_price_result' => 1_220_000,
            'buy_price' => 40, 'buy_price_currency' => 'USD', 'buy_price_result' => 488_000,
        ], $attrs));
        $t->forceFill(['created_by' => $this->operator()->id])->saveQuietly();

        return $t;
    }

    private function packageTransfer(string $when): Transfer
    {
        $tourId = DB::table('tours')->insertGetId([
            'group_number' => 'G'.uniqid(), 'company_id' => $this->company()->id, 'created_by' => $this->operator()->id, 'type' => 1,
        ]);
        $dayId = DB::table('tour_days')->insertGetId(['date' => $when, 'city_id' => $this->city()->id, 'tour_id' => $tourId]);
        $expenseId = DB::table('tour_day_expenses')->insertGetId(['type' => 3, 'tour_id' => $tourId, 'tour_day_id' => $dayId]);

        return $this->transfer($when.' 12:00', ['tour_day_expense_id' => $expenseId]);
    }

    private function expense(Transfer $t, int $amount, DriverExpenseStatus $status = DriverExpenseStatus::Approved): void
    {
        TransferDriverExpense::factory()->create([
            'transfer_id' => $t->id, 'driver_id' => Driver::factory()->create(['name' => 'Rustam'])->id, 'amount' => $amount, 'status' => $status,
        ]);
    }

    /** A realistic month: several sources, a loss, a gap in prices, expenses, a package transfer. */
    private function seedMonth(): array
    {
        $a = $this->transfer('2026-05-03 09:00');
        $b = $this->transfer('2026-05-10 14:00', ['status' => ExpenseStatus::Done, 'sell_price' => 300, 'sell_price_result' => 3_660_000]);
        $c = $this->transfer('2026-05-10 18:00', ['sell_price' => 20, 'sell_price_result' => 244_000]);          // loss
        $this->transfer('2026-05-20 10:00', ['sell_price' => null, 'sell_price_result' => null, 'buy_price' => null, 'buy_price_result' => null]);   // unpriced
        $this->transfer('2026-05-21 10:00', ['sell_price' => 220, 'sell_price_currency' => null, 'sell_price_result' => 220]);  // assumed dollars
        $this->packageTransfer('2026-05-25');
        $this->transfer('2026-06-02 10:00');                                                                      // next month
        $this->expense($a, 50_000);
        $this->expense($b, 20_000, DriverExpenseStatus::New);

        return [$a, $b, $c];
    }

    /** @return array{0: Spreadsheet, 1: array<string, mixed>} */
    private function workbook(string $from = '2026-05-01', string $to = '2026-06-30'): array
    {
        $report = TransferAnalytics::make()->build(Carbon::parse($from), Carbon::parse($to));
        $wb = W::build($report, 'Test Owner');
        W::write($wb, $this->path);

        return [IOFactory::load($this->path), $report];
    }

    private function cell(Spreadsheet $wb, string $sheetKey, string $address): mixed
    {
        return $wb->getSheetByName(__("analytics.sheets.{$sheetKey}"))->getCell($address)->getCalculatedValue();
    }

    // ── structure ─────────────────────────────────────────────────────────

    public function test_the_workbook_has_every_sheet_in_the_expected_order(): void
    {
        $this->seedMonth();

        [$wb] = $this->workbook();

        $expected = array_map(fn ($k) => __("analytics.sheets.{$k}"), ['summary', 'days', 'months', 'clients', 'operators', 'cities', 'drivers', 'expenses', 'detail', 'issues', 'method']);
        $this->assertSame($expected, $wb->getSheetNames());
    }

    public function test_sheet_names_are_valid_for_excel(): void
    {
        foreach (['ru', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach (__('analytics.sheets') as $name) {
                $this->assertLessThanOrEqual(31, mb_strlen($name), "'{$name}' is longer than Excel's 31 characters");
                $this->assertDoesNotMatchRegularExpression('/[:\\\\\/?*\[\]]/', $name, "'{$name}' has a character Excel forbids");
            }
        }
    }

    // ── the numbers, recomputed by Excel's own formula engine ─────────────

    public function test_the_summary_formulas_recalculate_to_exactly_what_the_service_computed(): void
    {
        $this->seedMonth();
        [$wb, $r] = $this->workbook();
        $s = $r['summary'];

        // Rows 8.. are: revenue, expenses, supplier, driver, package, profit, margin, avg, trips
        $this->assertEqualsWithDelta($s['revenue_uzs'], $this->cell($wb, 'summary', 'B8'), 0.01);
        $this->assertEqualsWithDelta($s['expenses_uzs'], $this->cell($wb, 'summary', 'B9'), 0.01);
        $this->assertEqualsWithDelta($s['supplier_uzs'], $this->cell($wb, 'summary', 'B10'), 0.01);
        $this->assertEqualsWithDelta($s['driver_exp_uzs'], $this->cell($wb, 'summary', 'B11'), 0.01);
        $this->assertEqualsWithDelta($s['package_cost_uzs'], $this->cell($wb, 'summary', 'B12'), 0.01);
        $this->assertEqualsWithDelta($s['profit_uzs'], $this->cell($wb, 'summary', 'B13'), 0.01);
        $this->assertEqualsWithDelta($s['margin'], $this->cell($wb, 'summary', 'B14'), 0.0001);
        $this->assertEqualsWithDelta($s['avg_revenue_per_trip_uzs'], $this->cell($wb, 'summary', 'B15'), 0.01);
        $this->assertSame($s['trips'], (int) $this->cell($wb, 'summary', 'B16'));

        // and USD
        $this->assertEqualsWithDelta($s['revenue_usd'], $this->cell($wb, 'summary', 'C8'), 0.01);
        $this->assertEqualsWithDelta($s['profit_usd'], $this->cell($wb, 'summary', 'C13'), 0.01);
    }

    public function test_expenses_total_is_the_sum_of_its_three_parts_in_the_sheet_too(): void
    {
        $this->seedMonth();
        [$wb] = $this->workbook();

        $parts = $this->cell($wb, 'summary', 'B10') + $this->cell($wb, 'summary', 'B11') + $this->cell($wb, 'summary', 'B12');

        $this->assertEqualsWithDelta($parts, $this->cell($wb, 'summary', 'B9'), 0.01);
    }

    public function test_coverage_block_matches_the_service(): void
    {
        $this->seedMonth();
        [$wb, $r] = $this->workbook();
        $s = $r['summary'];

        // Rows: coverage title 19, revenue-bearing trips 20, with price 21, with buy 22, important issues 23, all issues 24
        $this->assertSame($s['revenue_trips'], (int) $this->cell($wb, 'summary', 'B20'));
        $this->assertSame($s['revenue_trips'] - $s['unpriced'], (int) $this->cell($wb, 'summary', 'B21'));
        $this->assertEqualsWithDelta($s['priced_share'], $this->cell($wb, 'summary', 'C21'), 0.0001);
        $this->assertSame($s['revenue_trips'] - $s['no_buy'], (int) $this->cell($wb, 'summary', 'B22'));
        $important = count(array_filter($r['issues'], fn ($i) => $i['severity'] === 'warning'));
        $this->assertSame($important, (int) $this->cell($wb, 'summary', 'B23'), 'only the ones that distort totals');
        $this->assertGreaterThan(0, $important);
        $this->assertSame(count($r['issues']), (int) $this->cell($wb, 'summary', 'B24'));
    }

    public function test_the_by_source_table_recalculates_and_totals_match_the_headline(): void
    {
        $this->seedMonth();
        [$wb, $r] = $this->workbook();
        $sheet = $wb->getSheetByName(__('analytics.sheets.summary'));

        // find the source table by its first label
        $row = null;
        foreach (range(1, 60) as $i) {
            if ($sheet->getCell("A{$i}")->getValue() === __('analytics.sources.website')) {
                $row = $i;
                break;
            }
        }
        $this->assertNotNull($row, 'the by-source table is on the summary');

        foreach (array_keys($r['by_source']) as $n => $source) {
            $this->assertSame($r['by_source'][$source]['trips'], (int) $sheet->getCell('B'.($row + $n))->getCalculatedValue(), $source);
            $this->assertEqualsWithDelta($r['by_source'][$source]['revenue_uzs'], $sheet->getCell('C'.($row + $n))->getCalculatedValue(), 0.01, $source);
        }
        $totalRow = $row + count($r['by_source']);
        $this->assertEqualsWithDelta($r['summary']['revenue_uzs'], $sheet->getCell("C{$totalRow}")->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta($r['summary']['package_cost_uzs'], $sheet->getCell("H{$totalRow}")->getCalculatedValue(), 0.01);
    }

    public function test_no_sheet_contains_an_excel_error_after_recalculation(): void
    {
        $this->seedMonth();
        [$wb] = $this->workbook();

        $errors = [];
        foreach ($wb->getAllSheets() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $v = $cell->getCalculatedValue();
                    if (is_string($v) && preg_match('/^#(REF|NAME|VALUE|DIV\/0|N\/A|NUM|NULL)[!?]?/', $v)) {
                        $errors[] = $sheet->getTitle().'!'.$cell->getCoordinate().' = '.$v;
                    }
                }
            }
        }

        $this->assertSame([], $errors);
    }

    public function test_formulas_are_stored_with_cached_values_so_previewers_that_do_not_recalculate_show_numbers(): void
    {
        $this->seedMonth();
        [$wb, $r] = $this->workbook();
        $cell = $wb->getSheetByName(__('analytics.sheets.summary'))->getCell('B8');

        $this->assertStringStartsWith('=', (string) $cell->getValue(), 'it is a live formula');
        $this->assertEqualsWithDelta($r['summary']['revenue_uzs'], (float) $cell->getOldCalculatedValue(), 0.01, 'and its value is cached in the file');
    }

    // ── the period sheets ─────────────────────────────────────────────────

    public function test_the_daily_sheet_lists_every_day_and_its_total_row_matches(): void
    {
        $this->seedMonth();
        [$wb, $r] = $this->workbook('2026-05-01', '2026-05-31');
        $sheet = $wb->getSheetByName(__('analytics.sheets.days'));

        // header row 5, data from row 6: 31 days, total on row 37
        $this->assertSame(31, count($r['by_day']));
        $this->assertEqualsWithDelta($r['summary']['revenue_uzs'], $sheet->getCell('D37')->getCalculatedValue(), 0.01);
        $this->assertSame($r['summary']['trips'], (int) $sheet->getCell('C37')->getCalculatedValue());
        $this->assertSame(__('analytics.cols.total'), $sheet->getCell('A37')->getValue());
    }

    public function test_the_monthly_sheet_has_a_row_per_month_a_growth_formula_and_matching_totals(): void
    {
        $this->seedMonth();
        [$wb, $r] = $this->workbook('2026-05-01', '2026-06-30');
        $sheet = $wb->getSheetByName(__('analytics.sheets.months'));

        $this->assertSame(['2026-05', '2026-06'], array_keys($r['by_month']));
        $this->assertSame('Май 2026', $sheet->getCell('A6')->getValue(), 'capitalised even for Cyrillic');
        $this->assertEqualsWithDelta($r['summary']['revenue_uzs'], $sheet->getCell('C8')->getCalculatedValue(), 0.01);
        // growth of June over May = (June - May) / May
        $may = $r['by_month']['2026-05']['revenue_uzs'];
        $june = $r['by_month']['2026-06']['revenue_uzs'];
        $this->assertEqualsWithDelta(($june - $may) / $may, $sheet->getCell('H7')->getCalculatedValue(), 0.0001);
    }

    public function test_the_workbook_carries_the_charts(): void
    {
        $this->seedMonth();
        $report = TransferAnalytics::make()->build(Carbon::parse('2026-05-01'), Carbon::parse('2026-06-30'));
        $wb = W::build($report, 'Test Owner');

        $this->assertCount(1, $wb->getSheetByName(__('analytics.sheets.summary'))->getChartCollection());
        $this->assertCount(1, $wb->getSheetByName(__('analytics.sheets.days'))->getChartCollection());
        $this->assertCount(1, $wb->getSheetByName(__('analytics.sheets.months'))->getChartCollection());

        // ...and they survive being written to a file
        W::write($wb, $this->path);
        $zip = new \ZipArchive;
        $zip->open($this->path);
        $charts = array_filter(array_map(fn ($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1)), fn ($n) => str_starts_with($n, 'xl/charts/chart'));
        $zip->close();
        $this->assertCount(3, $charts);
    }

    // ── the detail sheet ──────────────────────────────────────────────────

    public function test_the_detail_sheet_has_a_row_per_transfer_with_filter_frozen_header_and_filter_aware_totals(): void
    {
        $this->seedMonth();
        [$wb, $r] = $this->workbook();
        $sheet = $wb->getSheetByName(__('analytics.sheets.detail'));

        $this->assertSame(count($r['rows']), (int) $sheet->getCell('A4')->getCalculatedValue(), 'SUBTOTAL(103) counts the rows');
        $this->assertStringContainsString('SUBTOTAL(109', (string) $sheet->getCell('M4')->getValue(), 'totals follow the filter');
        $this->assertSame('C6', $sheet->getFreezePane());
        $this->assertNotEmpty($sheet->getAutoFilter()->getRange());
        $this->assertSame($r['rows'][0]['number'], (int) $sheet->getCell('A6')->getValue());
    }

    public function test_a_package_transfers_revenue_side_is_zero_and_its_cost_is_in_the_package_columns(): void
    {
        $this->packageTransfer('2026-05-25');
        [$wb] = $this->workbook('2026-05-01', '2026-05-31');
        $sheet = $wb->getSheetByName(__('analytics.sheets.detail'));

        $this->assertSame(0, (int) $sheet->getCell('M6')->getValue(), 'no revenue');
        $this->assertSame(0, (int) $sheet->getCell('Q6')->getValue(), 'no supplier cost on the revenue side');
        $this->assertEqualsWithDelta(488_000.0, $sheet->getCell('W6')->getValue(), 0.01, 'the cost is reported as a package cost');
        $this->assertNull($sheet->getCell('U6')->getValue(), 'no profit is invented for it');
    }

    public function test_an_assumed_currency_is_starred_and_explained_in_the_notes(): void
    {
        $this->transfer('2026-05-21 10:00', ['sell_price' => 220, 'sell_price_currency' => null, 'sell_price_result' => 220]);
        [$wb] = $this->workbook('2026-05-01', '2026-05-31');
        $sheet = $wb->getSheetByName(__('analytics.sheets.detail'));

        $this->assertSame('USD*', $sheet->getCell('L6')->getValue());
        $this->assertStringContainsString(__('analytics.issues.currency_assumed'), (string) $sheet->getCell('Y6')->getValue());
    }

    // ── the data-check sheet ──────────────────────────────────────────────

    public function test_the_data_check_sheet_lists_each_issue_with_what_to_do_about_it(): void
    {
        $this->seedMonth();
        [$wb, $r] = $this->workbook();
        $sheet = $wb->getSheetByName(__('analytics.sheets.issues'));

        $problems = [];
        foreach (range(6, 5 + count($r['issues'])) as $row) {
            $problems[] = $sheet->getCell("B{$row}")->getValue();
            $this->assertNotEmpty($sheet->getCell("G{$row}")->getValue(), 'every row says what to do');
        }

        $this->assertContains(__('analytics.issues.no_sell'), $problems);
        $this->assertContains(__('analytics.issues.loss'), $problems);
        $this->assertContains(__('analytics.issues.currency_assumed'), $problems);
        $this->assertSame(__('analytics.severity.warning'), $sheet->getCell('A6')->getValue(), 'the serious ones come first');
    }

    // ── the coverage warning ──────────────────────────────────────────────

    public function test_the_summary_warns_loudly_when_most_transfers_have_no_price(): void
    {
        foreach (range(1, 4) as $i) {
            $this->transfer("2026-05-0{$i} 10:00", ['sell_price' => null, 'sell_price_result' => null]);
        }
        $this->transfer('2026-05-10 10:00');

        [$wb] = $this->workbook('2026-05-01', '2026-05-31');
        $sheet = $wb->getSheetByName(__('analytics.sheets.summary'));

        $found = false;
        foreach (range(15, 30) as $row) {
            if (str_starts_with((string) $sheet->getCell("A{$row}")->getValue(), 'Внимание')) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'the amber warning is on the first screen');
    }

    public function test_no_warning_when_coverage_is_good(): void
    {
        foreach (range(1, 5) as $i) {
            $this->transfer("2026-05-0{$i} 10:00");
        }

        [$wb] = $this->workbook('2026-05-01', '2026-05-31');
        $sheet = $wb->getSheetByName(__('analytics.sheets.summary'));

        foreach (range(15, 30) as $row) {
            $this->assertStringStartsNotWith('Внимание', (string) $sheet->getCell("A{$row}")->getValue());
        }
    }

    // ── edge cases ────────────────────────────────────────────────────────

    public function test_an_empty_period_still_produces_a_valid_workbook_that_says_so(): void
    {
        [$wb] = $this->workbook('2026-05-01', '2026-05-31');
        $sheet = $wb->getSheetByName(__('analytics.sheets.summary'));

        $this->assertSame(__('analytics.summary.no_data'), $sheet->getCell('A6')->getValue());
        $this->assertCount(11, $wb->getSheetNames());
    }

    public function test_it_works_in_english_too(): void
    {
        app()->setLocale('en');
        $this->seedMonth();

        [$wb] = $this->workbook();

        $this->assertSame('Summary', $wb->getSheetNames()[0]);
        $this->assertSame('May 2026', $wb->getSheetByName('By month')->getCell('A6')->getValue());
    }

    public function test_every_translation_key_used_by_the_report_exists_in_russian_and_english(): void
    {
        $sources = file_get_contents(app_path('Services/Analytics/TransferAnalyticsWorkbook.php'));
        preg_match_all("/analytics\.([a-z_]+(?:\.[a-z_]+)*)/", $sources, $m);
        $keys = array_unique($m[1]);

        foreach (['ru', 'en'] as $locale) {
            foreach ($keys as $key) {
                // keys built with a variable (analytics.cols.{$x}) are checked by their prefix elsewhere
                if (str_ends_with($key, '.') || str_contains($key, '{')) {
                    continue;
                }
                $this->assertTrue(\Illuminate\Support\Facades\Lang::has("analytics.{$key}", $locale, false), "analytics.{$key} is missing in {$locale}");
            }
        }
    }

    public function test_dynamic_keys_exist_for_every_source_status_and_issue_code(): void
    {
        foreach (['ru', 'en'] as $locale) {
            foreach (TransferAnalytics::SOURCES as $source) {
                foreach (['sources', 'source_hint'] as $group) {
                    $this->assertTrue(\Illuminate\Support\Facades\Lang::has("analytics.{$group}.{$source}", $locale, false), "{$group}.{$source} ({$locale})");
                }
            }
            foreach (['no_sell', 'no_buy', 'currency_assumed', 'no_rate', 'usd_repriced', 'loss', 'expenses_pending'] as $code) {
                foreach (['issues', 'issue_action'] as $group) {
                    $this->assertTrue(\Illuminate\Support\Facades\Lang::has("analytics.{$group}.{$code}", $locale, false), "{$group}.{$code} ({$locale})");
                }
            }
        }
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Filament\Resources\TransferResource\Pages\ListTransfers;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Transfer;
use App\Models\User;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * The operators' transfer list: date/time and the pickup sign in the cells, day grouping, real sorting, search
 * and the Excel export that mirrors the (packed) cells without their markup.
 */
class TransferListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        CacheService::$cache = [];
        app()->setLocale('ru');
        $this->admin = User::factory()->create(['role' => 0]);
        $this->actingAs($this->admin);
    }

    private function transfer(string $when, array $attrs = []): Transfer
    {
        // The number is assigned when a transfer is created (1001, 1002, ...), so a number a test wants is set afterwards.
        $number = $attrs['number'] ?? null;
        unset($attrs['number']);

        $transfer = Transfer::factory()->create(array_merge(['date_time' => Carbon::parse($when)], $attrs));

        if ($number !== null) {
            Transfer::query()->whereKey($transfer->id)->update(['number' => $number]);
            $transfer->refresh();
        }

        return $transfer;
    }

    private function list(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(ListTransfers::class);
    }

    // ── the cells ─────────────────────────────────────────────────────────

    public function test_date_and_time_are_shown_on_two_labelled_lines(): void
    {
        $t = $this->transfer('2026-09-14 18:20');

        $this->list()
            ->assertCanSeeTableRecords([$t])
            ->assertSeeHtml('<span class="ep-kv__k">'.__('Date').'</span>14.09.2026')
            ->assertSeeHtml('<span class="ep-kv__k">'.__('Time').'</span>18:20');
    }

    public function test_a_transfer_without_a_date_does_not_break_the_list(): void
    {
        $t = $this->transfer('2026-09-14 10:00');
        Transfer::query()->whereKey($t->id)->update(['date_time' => null]);   // the form does not require a date

        $this->list()->assertSuccessful()->assertCanSeeTableRecords([$t]);
    }

    public function test_the_pickup_sign_is_shown_under_the_passenger_count(): void
    {
        $t = $this->transfer('2026-09-14 10:00', ['pax' => 3, 'nameplate' => 'MR SMITH']);

        $this->list()->assertCanSeeTableRecords([$t])->assertSee('MR SMITH');
    }

    /**
     * A cell draws its second line only when its own top value is not empty. Without a placeholder a transfer that
     * has a tour but no number (21% of the real data has no number) would silently lose the tour.
     */
    public function test_a_second_line_survives_a_missing_top_value(): void
    {
        $city = City::query()->create(['name' => 'Bukhara', 'country_id' => Country::firstOrCreate(['name' => 'Uzbekistan'])->id]);
        $t = $this->transfer('2026-09-14 10:00', [
            'group_number' => '25-777', 'route' => null, 'to_city_id' => $city->id,
            'driver_name' => null, 'driver_phone' => '+998901112233',
            'sell_price' => null, 'buy_price' => 40, 'nameplate' => 'MR JONES', 'pax' => null, 'requested_by' => 'Dilnoza', 'company_id' => null,
        ]);
        Transfer::query()->whereKey($t->id)->update(['number' => null]);

        $this->list()
            ->assertCanSeeTableRecords([$t])
            ->assertSee('25-777')            // tour, no number
            ->assertSee('Bukhara')           // city, no route
            ->assertSee('+998901112233')     // driver phone, no driver name
            ->assertSee(__('Buy price').': $40')   // purchase price, no sell price
            ->assertSee('MR JONES')          // sign, no pax
            ->assertSee('Dilnoza');          // requester, no company
    }

    public function test_empty_second_lines_are_not_drawn(): void
    {
        $this->transfer('2026-09-14 10:00', ['nameplate' => null, 'requested_by' => null, 'driver_phone' => null, 'buy_price' => null]);

        $bare = substr_count($this->list()->set('tableGrouping', null)->html(), 'text-sm text-gray-500 dark:text-gray-400');

        Transfer::query()->update(['nameplate' => 'MR X', 'requested_by' => 'Dilnoza', 'driver_phone' => '+998901112233', 'buy_price' => 5, 'group_number' => '25-1']);
        $full = substr_count($this->list()->set('tableGrouping', null)->html(), 'text-sm text-gray-500 dark:text-gray-400');

        $this->assertSame(5, $full - $bare, 'sign, requester, driver phone, buy price and tour appear only when there is something to say');
    }

    public function test_free_text_is_escaped(): void
    {
        $this->transfer('2026-09-14 10:00', [
            'nameplate' => '<script>alert(1)</script>',
            'route' => '<img src=x onerror=alert(2)>',
            'requested_by' => '<b>boss</b>',
        ]);

        $this->list()
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertDontSeeHtml('<img src=x')
            ->assertDontSeeHtml('<b>boss</b>')
            ->assertSeeHtml('&lt;script&gt;');
    }

    public function test_the_tour_number_and_the_city_sit_under_the_transfer_number_and_route(): void
    {
        $city = City::query()->create(['name' => 'Samarkand', 'country_id' => Country::firstOrCreate(['name' => 'Uzbekistan'])->id]);
        $t = $this->transfer('2026-09-14 10:00', ['number' => 'TR-1', 'group_number' => '25-118', 'to_city_id' => $city->id]);

        $this->list()->assertCanSeeTableRecords([$t])->assertSee('25-118')->assertSee('Samarkand');
    }

    public function test_the_creation_columns_are_hidden_until_switched_on(): void
    {
        $this->transfer('2026-09-14 10:00');

        $this->list()
            ->assertSet('toggledTableColumns.created_at', false)
            ->set('toggledTableColumns.created_at', true)
            ->assertSuccessful();
    }

    // ── order and grouping ────────────────────────────────────────────────

    public function test_the_list_opens_grouped_by_day(): void
    {
        $this->transfer('2026-09-14 10:00');

        $this->list()->assertSet('tableGrouping', 'date_time');
    }

    public function test_days_run_from_today_onwards_and_then_the_past_nearest_first(): void
    {
        $lastYear = $this->transfer(now()->subYear()->format('Y-m-d').' 10:00');
        $lastWeek = $this->transfer(now()->subWeek()->format('Y-m-d').' 10:00');
        $nextWeek = $this->transfer(now()->addWeek()->format('Y-m-d').' 10:00');
        $today = $this->transfer(now()->format('Y-m-d').' 10:00');

        $this->list()->assertCanSeeTableRecords([$today, $nextWeek, $lastWeek, $lastYear], inOrder: true);
    }

    public function test_ungrouped_the_old_order_still_applies(): void
    {
        $past = $this->transfer(now()->subMonth()->format('Y-m-d').' 10:00');
        $soon = $this->transfer(now()->addDay()->format('Y-m-d').' 10:00');
        $later = $this->transfer(now()->addMonth()->format('Y-m-d').' 10:00');

        $this->list()->set('tableGrouping', null)->assertCanSeeTableRecords([$soon, $later, $past], inOrder: true);
    }

    public function test_inside_a_day_transfers_are_in_time_order(): void
    {
        $evening = $this->transfer('2026-09-14 21:00', ['number' => 'B']);
        $morning = $this->transfer('2026-09-14 07:00', ['number' => 'C']);
        $noon = $this->transfer('2026-09-14 12:00', ['number' => 'A']);

        $this->list()->assertCanSeeTableRecords([$morning, $noon, $evening], inOrder: true);
    }

    /** The bug this task fixes: the arrows on the column headings did nothing. */
    public function test_clicking_a_column_heading_really_sorts(): void
    {
        $c = $this->transfer('2026-09-14 10:00', ['number' => 'C3']);
        $a = $this->transfer('2026-09-14 11:00', ['number' => 'A1']);
        $b = $this->transfer('2026-09-14 12:00', ['number' => 'B2']);

        $this->list()
            ->assertCanSeeTableRecords([$c, $a, $b], inOrder: true)          // by time, as opened
            ->sortTable('number')
            ->assertCanSeeTableRecords([$a, $b, $c], inOrder: true)
            ->sortTable('number', 'desc')
            ->assertCanSeeTableRecords([$c, $b, $a], inOrder: true);
    }

    public function test_sorting_also_works_when_the_list_is_not_grouped(): void
    {
        $c = $this->transfer('2026-09-14 10:00', ['number' => 'C3']);
        $a = $this->transfer('2026-09-15 10:00', ['number' => 'A1']);
        $b = $this->transfer('2026-09-16 10:00', ['number' => 'B2']);

        $this->list()
            ->set('tableGrouping', null)
            ->sortTable('number')
            ->assertCanSeeTableRecords([$a, $b, $c], inOrder: true);
    }

    public function test_a_day_heading_counts_the_whole_day_not_just_the_page(): void
    {
        foreach (range(1, 35) as $i) {
            $this->transfer('2026-09-14 10:00', ['number' => "N{$i}"]);
        }
        $this->transfer('2026-09-15 10:00');

        $list = $this->list();

        $this->assertCount(30, $list->instance()->getTableRecords()->items(), 'one page');
        $list->assertSee(__('Transfers').': 35');                           // ...but the day has 35
    }

    public function test_the_day_count_follows_the_filters(): void
    {
        $confirmed = $this->transfer('2026-09-14 10:00', ['status' => ExpenseStatus::Confirmed]);
        $this->transfer('2026-09-14 11:00', ['status' => ExpenseStatus::Rejected]);
        $this->transfer('2026-09-14 12:00', ['status' => ExpenseStatus::Rejected]);

        $this->list()
            ->filterTable('today', [
                'today' => false, 'tomorrow' => false, 'driver_ids' => null, 'companies' => null,
                'statuses' => [ExpenseStatus::Confirmed->value], 'driver_statuses' => null, 'date_from' => null, 'date_until' => null,
            ])
            ->assertCanSeeTableRecords([$confirmed])
            ->assertSee(__('Transfers').': 1')
            ->assertDontSee(__('Transfers').': 3');
    }

    // ── search ────────────────────────────────────────────────────────────

    public function test_search_finds_a_transfer_by_sign_company_route_driver_and_number(): void
    {
        $company = Company::query()->create(['name' => 'Silk Road Tours']);
        $t = $this->transfer('2026-09-14 10:00', [
            'nameplate' => 'ACWA POWER', 'route' => 'Airport to Hyatt', 'driver_name' => 'Rustam', 'number' => 'TR-777',
            'company_id' => $company->id, 'requested_by' => 'Dilnoza',
        ]);
        $other = $this->transfer('2026-09-14 11:00', ['nameplate' => 'someone else', 'route' => 'Elsewhere', 'number' => 'TR-1']);

        foreach (['acwa', 'silk road', 'hyatt', 'rustam', 'TR-777', 'dilnoza'] as $term) {
            $this->list()
                ->searchTable($term)
                ->assertCanSeeTableRecords([$t])
                ->assertCanNotSeeTableRecords([$other]);
        }
    }

    public function test_search_finds_a_transfer_by_the_tour_number(): void
    {
        $t = $this->transfer('2026-09-14 10:00', ['group_number' => '25-118']);
        $other = $this->transfer('2026-09-14 11:00', ['group_number' => '25-999']);

        $this->list()->searchTable('25-118')->assertCanSeeTableRecords([$t])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_like_wildcards_typed_in_search_are_taken_literally(): void
    {
        $a = $this->transfer('2026-09-14 10:00', ['route' => 'Airport']);
        $b = $this->transfer('2026-09-14 11:00', ['route' => '100% discount']);

        $this->list()->searchTable('%')->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
    }

    // ── access ────────────────────────────────────────────────────────────

    public function test_an_operator_still_sees_only_the_transfers_they_created(): void
    {
        $mine = null;
        $theirs = $this->transfer('2026-09-14 10:00');                       // created by the admin

        $operator = User::factory()->create(['role' => 1]);
        $this->actingAs($operator);
        $mine = $this->transfer('2026-09-14 11:00');

        $this->list()->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
    }

    // ── Excel export ──────────────────────────────────────────────────────

    /** @return array<int, array<int, mixed>> the exported sheet as rows */
    private function exportRows(?callable $prepare = null): array
    {
        $test = $this->list();
        if ($prepare) {
            $prepare($test);
        }

        $test->callAction('export')->assertHasNoActionErrors();

        $download = $test->effects['download'] ?? null;
        $this->assertNotNull($download, 'the export must answer with a file');

        $path = tempnam(sys_get_temp_dir(), 'transfers').'.xlsx';
        file_put_contents($path, base64_decode($download['content']));

        try {
            return IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        } finally {
            @unlink($path);
        }
    }

    public function test_the_export_has_one_plain_field_per_column_and_no_markup(): void
    {
        $city = City::query()->create(['name' => 'Samarkand', 'country_id' => Country::firstOrCreate(['name' => 'Uzbekistan'])->id]);
        $this->transfer('2026-09-14 18:20', [
            'number' => 'TR-1', 'group_number' => '25-118', 'nameplate' => 'MR SMITH', 'route' => 'Airport',
            'driver_name' => 'Rustam', 'driver_phone' => '+998901112233', 'to_city_id' => $city->id, 'requested_by' => 'Dilnoza',
            'sell_price' => 100, 'buy_price' => 40,
        ]);

        $rows = $this->exportRows();
        $header = array_map('strval', $rows[0]);
        $data = array_combine($header, $rows[1]);

        $this->assertSame('TR-1', $data[__('Number')]);
        $this->assertSame('25-118', (string) $data[__('Tour')]);
        $this->assertSame('14.09.2026', $data[__('Date')]);
        $this->assertSame('18:20', $data[__('Time')]);
        $this->assertSame('MR SMITH', $data[__('Табличка')]);
        $this->assertSame('Samarkand', $data[__('Location')]);
        $this->assertSame('Dilnoza', $data[__('Requested by')]);
        $this->assertSame('Rustam', $data[__('Driver name')]);
        $this->assertSame('+998 90 111 22 33', $data[__('Driver phone number')], 'kept as text: a bare +998... would become a number');
        $this->assertEquals(40, $data[__('Buy price')]);

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $this->assertStringNotContainsString('<span', (string) $cell);
                $this->assertStringNotContainsString('class=', (string) $cell);
            }
        }
    }

    public function test_the_export_keeps_the_columns_the_list_hides_and_respects_the_search(): void
    {
        $this->transfer('2026-09-14 10:00', ['nameplate' => 'KEEP ME']);
        $this->transfer('2026-09-14 11:00', ['nameplate' => 'DROP ME']);

        $rows = $this->exportRows(fn ($test) => $test->searchTable('keep'));

        $this->assertCount(2, $rows, 'the header and the one matching transfer');
        $this->assertContains(__('Created by'), array_map('strval', $rows[0]), 'a column hidden in the list is still exported');
    }
}

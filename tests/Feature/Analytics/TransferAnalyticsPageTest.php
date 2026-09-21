<?php

namespace Tests\Feature\Analytics;

use App\Enums\ExpenseStatus;
use App\Filament\Pages\TransferAnalyticsPage;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Transfer;
use App\Models\User;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** Who may open the report, what the page shows, and that the Excel download is a real, correctly scoped file. */
class TransferAnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CacheService::$cache = [];
        app()->setLocale('ru');
        Currency::create(['from' => 'UZS', 'to' => 'USD', 'rate' => 12200, 'is_main' => true]);
    }

    private function as(int $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    private function transfer(string $when, array $attrs = []): Transfer
    {
        $city = City::query()->firstOrCreate(['name' => 'Samarkand'], ['country_id' => Country::firstOrCreate(['name' => 'Uzbekistan'])->id]);

        return Transfer::factory()->create(array_merge([
            'date_time' => Carbon::parse($when), 'status' => ExpenseStatus::Confirmed, 'pax' => 2, 'driver_ids' => [],
            'company_id' => Company::query()->firstOrCreate(['name' => 'Acme Travel'])->id, 'to_city_id' => $city->id,
            'sell_price' => 100, 'sell_price_currency' => 'USD', 'sell_price_result' => 1_220_000,
            'buy_price' => 40, 'buy_price_currency' => 'USD', 'buy_price_result' => 488_000,
        ], $attrs));
    }

    private function query(array $q = []): array
    {
        return array_merge(['from' => '2026-05-01', 'until' => '2026-05-31'], $q);
    }

    // ── access ────────────────────────────────────────────────────────────

    public function test_admin_and_accountant_may_open_the_page(): void
    {
        foreach ([0, 2] as $role) {
            $this->as($role);
            $this->assertTrue(TransferAnalyticsPage::canAccess(), "role {$role}");
        }
    }

    public function test_operators_senior_operators_and_guests_may_not(): void
    {
        foreach ([1, 3] as $role) {
            $this->as($role);
            $this->assertFalse(TransferAnalyticsPage::canAccess(), "role {$role}");
        }

        auth()->logout();
        $this->assertFalse(TransferAnalyticsPage::canAccess());
    }

    public function test_the_page_route_is_forbidden_to_an_operator_and_open_to_an_admin(): void
    {
        $this->as(1);
        $this->get('/admin/transfer-analytics')->assertForbidden();

        $this->as(0);
        $this->get('/admin/transfer-analytics')->assertOk();
    }

    public function test_the_export_is_forbidden_to_everyone_but_admin_and_accountant(): void
    {
        $this->get(route('admin.transfer-analytics.export', $this->query()))->assertForbidden();   // guest

        foreach ([1, 3] as $role) {
            $this->as($role);
            $this->get(route('admin.transfer-analytics.export', $this->query()))->assertForbidden();
        }
    }

    // ── page ──────────────────────────────────────────────────────────────

    public function test_the_page_shows_the_totals_for_the_chosen_period(): void
    {
        $this->as(0);
        $this->transfer('2026-05-10 10:00');
        $this->transfer('2026-05-11 10:00', ['sell_price' => 300, 'sell_price_result' => 3_660_000]);
        $this->transfer('2026-06-10 10:00');                                     // another month: must not appear

        Livewire::test(TransferAnalyticsPage::class)
            ->set('data.from', '2026-05-01')->set('data.until', '2026-05-31')
            ->assertSee('4 880 000')        // 1 220 000 + 3 660 000 revenue
            ->assertSee('Аналитика трансферов');
    }

    public function test_an_empty_period_says_so_instead_of_showing_zeros(): void
    {
        $this->as(0);

        Livewire::test(TransferAnalyticsPage::class)
            ->set('data.from', '2020-01-01')->set('data.until', '2020-01-31')
            ->assertSee(__('analytics.summary.no_data'));
    }

    public function test_the_source_filter_narrows_the_report(): void
    {
        $this->as(0);
        $this->transfer('2026-05-10 10:00');                                     // manual (no request, no tour)

        Livewire::test(TransferAnalyticsPage::class)
            ->set('data.from', '2026-05-01')->set('data.until', '2026-05-31')
            ->set('data.sources', ['website'])
            ->assertSee(__('analytics.summary.no_data'));
    }

    public function test_an_unpriced_month_shows_the_coverage_warning(): void
    {
        $this->as(0);
        $this->transfer('2026-05-10 10:00');
        $this->transfer('2026-05-11 10:00', ['sell_price' => null, 'sell_price_result' => null]);

        Livewire::test(TransferAnalyticsPage::class)
            ->set('data.from', '2026-05-01')->set('data.until', '2026-05-31')
            ->assertSeeHtml('role="alert"');
    }

    public function test_a_reversed_or_junk_period_does_not_crash(): void
    {
        $this->as(0);
        $this->transfer('2026-05-10 10:00');

        Livewire::test(TransferAnalyticsPage::class)
            ->set('data.from', '2026-05-31')->set('data.until', '2026-05-01')        // reversed: swapped
            ->assertSee('1 220 000')
            ->set('data.from', 'not a date')                                        // junk: falls back to the default
            ->assertOk();
    }

    public function test_a_period_that_is_too_long_is_cut_and_says_so(): void
    {
        $this->as(0);

        Livewire::test(TransferAnalyticsPage::class)
            ->set('data.from', '2010-01-01')->set('data.until', '2026-12-31')
            ->assertSee(__('analytics.page.too_long', ['days' => 800]));
    }

    public function test_choosing_a_quick_period_fills_the_dates(): void
    {
        $this->as(0);

        $expected = \App\Support\AnalyticsPeriod::dates('last_month');

        Livewire::test(TransferAnalyticsPage::class)
            ->set('data.preset', 'last_month')
            ->assertSet('data.from', $expected[0]->toDateString())
            ->assertSet('data.until', $expected[1]->toDateString());
    }

    public function test_the_download_button_carries_the_filters_on_screen(): void
    {
        $this->as(0);

        $url = Livewire::test(TransferAnalyticsPage::class)
            ->set('data.from', '2026-05-01')->set('data.until', '2026-05-31')
            ->set('data.sources', ['manual', 'website'])
            ->instance()->exportUrl();

        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('2026-05-01', $q['from']);
        $this->assertSame('2026-05-31', $q['until']);
        $this->assertSame(['website', 'manual'], $q['sources']);
    }

    // ── export ────────────────────────────────────────────────────────────

    public function test_an_accountant_downloads_a_real_workbook_with_a_sensible_name(): void
    {
        $this->as(2);
        $this->transfer('2026-05-10 10:00');

        $response = $this->get(route('admin.transfer-analytics.export', $this->query()));

        $response->assertOk();
        $this->assertStringContainsString('transfer-report_2026-05-01_2026-05-31.xlsx', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));

        $file = $response->baseResponse->getFile()->getPathname();
        $sheets = IOFactory::load($file)->getSheetNames();
        $this->assertContains(__('analytics.sheets.summary'), $sheets);
        $this->assertContains(__('analytics.sheets.detail'), $sheets);
    }

    public function test_the_export_respects_the_source_filter(): void
    {
        $this->as(0);
        $this->transfer('2026-05-10 10:00');                                     // manual

        $file = $this->get(route('admin.transfer-analytics.export', $this->query(['sources' => ['website']])))
            ->assertOk()->baseResponse->getFile()->getPathname();

        $detail = IOFactory::load($file)->getSheetByName(__('analytics.sheets.detail'));
        $this->assertNull($detail->getCell('A6')->getValue(), 'no transfer of that source: the detail sheet has no data rows');
    }

    public function test_the_export_rejects_bad_input(): void
    {
        $this->as(0);

        $this->getJson(route('admin.transfer-analytics.export'))->assertStatus(422);
        $this->getJson(route('admin.transfer-analytics.export', $this->query(['from' => '31.05.2026'])))->assertStatus(422);
        $this->getJson(route('admin.transfer-analytics.export', $this->query(['from' => '2026-06-01', 'until' => '2026-05-01'])))->assertStatus(422);
        $this->getJson(route('admin.transfer-analytics.export', $this->query(['sources' => ['hacker']])))->assertStatus(422);
        $this->get(route('admin.transfer-analytics.export', ['from' => '2010-01-01', 'until' => '2026-12-31']))->assertStatus(422);
    }

    public function test_the_download_streams_an_xlsx_and_leaves_no_temporary_file_behind(): void
    {
        $this->as(0);
        $this->transfer('2026-05-10 10:00');

        $response = $this->get(route('admin.transfer-analytics.export', $this->query()))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);

        ob_start();
        $response->baseResponse->sendContent();   // what the server does; the file is deleted once it has been sent
        $sent = ob_get_clean();

        $this->assertSame('PK', substr($sent, 0, 2), 'an .xlsx is a zip archive');
        $this->assertFileDoesNotExist($path);
    }
}

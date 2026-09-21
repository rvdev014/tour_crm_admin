<?php

namespace Tests\Feature\Driver;

use App\Enums\DriverTransferStatus as S;
use App\Enums\ExpenseStatus;
use App\Models\City;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverStatusLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DriverCabinetTest extends TestCase
{
    use RefreshDatabase;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed clock: the cabinet's "today" is Asia/Tashkent, and the app timezone is UTC.
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00', 'Asia/Tashkent'));

        $this->driver = Driver::factory()->create(['chat_id' => '123456789']);
    }

    private function transfer(array $attrs = [], ?Driver ...$drivers): Transfer
    {
        $drivers = $drivers ?: [$this->driver];

        return Transfer::factory()->forDrivers(...array_filter($drivers))->create(array_merge([
            'date_time' => Carbon::parse('2026-09-21 14:30:00', 'Asia/Tashkent'),
        ], $attrs));
    }

    private function asDriver(?Driver $driver = null): static
    {
        return $this->actingAs($driver ?? $this->driver, 'driver');
    }

    // ── list ──────────────────────────────────────────────────────────────

    public function test_list_shows_todays_own_transfers_with_time_and_destination(): void
    {
        $this->transfer(['route' => 'Registon Plaza']);

        $this->asDriver()->get(route('driver.transfers'))
            ->assertOk()
            ->assertSee('14:30')
            ->assertSee('Registon Plaza');
    }

    public function test_list_never_shows_another_drivers_transfer(): void
    {
        $other = Driver::factory()->create();
        $this->transfer(['route' => 'Somebody Elses Trip'], $other);

        $this->asDriver()->get(route('driver.transfers'))
            ->assertOk()
            ->assertDontSee('Somebody Elses Trip');
    }

    public function test_list_only_shows_the_selected_day(): void
    {
        $this->transfer(['route' => 'Today Trip']);
        $this->transfer(['route' => 'Tomorrow Trip', 'date_time' => Carbon::parse('2026-09-22 09:00', 'Asia/Tashkent')]);

        $this->asDriver()->get(route('driver.transfers', ['date' => '2026-09-22']))
            ->assertOk()
            ->assertSee('Tomorrow Trip')
            ->assertDontSee('Today Trip');
    }

    public function test_a_transfer_at_2359_belongs_to_its_own_day(): void
    {
        $this->transfer(['route' => 'Late Trip', 'date_time' => Carbon::parse('2026-09-21 23:59:00', 'Asia/Tashkent')]);

        $this->asDriver()->get(route('driver.transfers', ['date' => '2026-09-21']))->assertSee('Late Trip');
        $this->asDriver()->get(route('driver.transfers', ['date' => '2026-09-22']))->assertDontSee('Late Trip');
    }

    /** @dataProvider badDates */
    public function test_a_malformed_date_falls_back_to_today_instead_of_erroring(string $date): void
    {
        $this->transfer(['route' => 'Today Trip']);

        $this->asDriver()->get(route('driver.transfers', ['date' => $date]))
            ->assertOk()
            ->assertSee('Today Trip');
    }

    public static function badDates(): array
    {
        return [
            'garbage' => ['not-a-date'],
            'impossible day' => ['2026-02-31'],
            'wrong format' => ['21.09.2026'],
            'sql-ish' => ["2026-09-21' OR 1=1 --"],
        ];
    }

    public function test_the_date_strip_marks_days_that_have_transfers(): void
    {
        $this->transfer();

        $html = $this->asDriver()->get(route('driver.transfers'))->assertOk()->getContent();

        // Window when viewing today: yesterday .. today+5 = 7 chips; only today has a transfer.
        // Match class attributes only — the inline <style> also contains these class names.
        $chips = preg_match_all('/class="(day [^"]*)"/', $html, $m) ? $m[1] : [];

        $this->assertCount(7, $chips);
        $this->assertCount(6, array_filter($chips, fn ($c) => str_contains($c, 'day--empty')));
        $this->assertCount(1, array_filter($chips, fn ($c) => str_contains($c, 'day--active')));
        $this->assertCount(1, array_filter($chips, fn ($c) => str_contains($c, 'day--today')));
    }

    public function test_empty_day_shows_an_empty_state(): void
    {
        $this->asDriver()->get(route('driver.transfers'))
            ->assertOk()
            ->assertSee(__('driver.list.empty'));
    }

    public function test_only_confirmed_and_done_transfers_are_listed(): void
    {
        $this->transfer(['route' => 'Confirmed Trip', 'status' => ExpenseStatus::Confirmed]);
        $this->transfer(['route' => 'Done Trip', 'status' => ExpenseStatus::Done]);
        $this->transfer(['route' => 'New Trip', 'status' => ExpenseStatus::New]);
        $this->transfer(['route' => 'Rejected Trip', 'status' => ExpenseStatus::Rejected]);

        $this->asDriver()->get(route('driver.transfers'))
            ->assertSee('Confirmed Trip')
            ->assertSee('Done Trip')
            ->assertDontSee('New Trip')
            ->assertDontSee('Rejected Trip');
    }

    // ── detail / ownership ────────────────────────────────────────────────

    public function test_detail_shows_the_trip_details(): void
    {
        $t = $this->transfer([
            'route' => 'Registon Plaza',
            'place_of_submission' => 'Samarkand Airport, terminal 2',
            'nameplate' => 'MR SMITH',
            'pax' => 3,
            'comment' => 'Child seat needed',
        ]);

        $this->asDriver()->get(route('driver.transfers.show', $t->id))
            ->assertOk()
            ->assertSee('Registon Plaza')
            ->assertSee('Samarkand Airport, terminal 2')
            ->assertSee('MR SMITH')
            ->assertSee('Child seat needed')
            ->assertSee(1000 + $t->id);
    }

    public function test_another_drivers_transfer_is_a_404_not_a_403(): void
    {
        $t = $this->transfer([], Driver::factory()->create());

        $this->asDriver()->get(route('driver.transfers.show', $t->id))->assertNotFound();
        $this->asDriver()->get(route('driver.transfers.complete', $t->id))->assertNotFound();
        $this->asDriver()->post(route('driver.transfers.status', $t->id), [
            'from' => 'assigned', 'to' => 'en_route_to_client',
        ])->assertNotFound();

        $this->assertNull($t->fresh()->driver_status);
    }

    public function test_a_nonexistent_transfer_is_a_404(): void
    {
        $this->asDriver()->get(route('driver.transfers.show', 999999))->assertNotFound();
    }

    public function test_a_new_or_rejected_transfer_cannot_be_opened_by_direct_link(): void
    {
        $new = $this->transfer(['status' => ExpenseStatus::New]);
        $rejected = $this->transfer(['status' => ExpenseStatus::Rejected]);

        $this->asDriver()->get(route('driver.transfers.show', $new->id))->assertNotFound();
        $this->asDriver()->get(route('driver.transfers.show', $rejected->id))->assertNotFound();
    }

    public function test_the_cabinet_binder_does_not_hijack_the_exports_transfer_parameter(): void
    {
        // Regression: Route::bind() is global by parameter name, and `export-transfer/{transfer}` in
        // routes/web.php relies on ordinary implicit model binding. The cabinet therefore binds
        // `driverTransfer`; a custom binder on plain `transfer` would break the voucher exports.
        $router = app('router');

        $this->assertNull($router->getBindingCallback('transfer'));
        $this->assertNotNull($router->getBindingCallback('driverTransfer'));
        $this->assertContains('transfer', app('router')->getRoutes()->getByName('export-transfer')->parameterNames());
    }

    // ── prices ────────────────────────────────────────────────────────────

    public function test_prices_and_the_client_company_are_never_rendered(): void
    {
        $company = Company::query()->forceCreate(['name' => 'SECRET-COMPANY-LLC']);
        $t = $this->transfer([
            'company_id' => $company->id,
            // total_price is numeric(8,2): must stay below 10^6.
            'price' => 111111.11,
            'total_price' => 222222.22,
            'sell_price' => 3333333.33,
            'buy_price' => 4444444.44,
            'sell_price_result' => 5555555,
            'buy_price_result' => 6666666,
            'sell_price_currency' => 'USD-SECRET',
            'requested_by' => 'SECRET-REQUESTER',
            'group_number' => 'SECRET-GROUP-99',
        ]);

        $pages = [
            'list' => $this->asDriver()->get(route('driver.transfers'))->assertOk()->getContent(),
            'detail' => $this->asDriver()->get(route('driver.transfers.show', $t->id))->assertOk()->getContent(),
        ];

        // Walk to the confirmation page too, so every view is covered.
        foreach ([S::EnRouteToClient, S::WaitingForClient, S::OnTheWay] as $to) {
            $this->post(route('driver.transfers.status', $t->id), ['from' => $t->fresh()->effectiveDriverStatus()->value, 'to' => $to->value]);
        }
        $pages['complete'] = $this->get(route('driver.transfers.complete', $t->id))->assertOk()->getContent();

        foreach ($pages as $name => $html) {
            foreach ([
                '111111', '222222', '3333333', '4444444', '5555555', '6666666',
                'USD-SECRET', 'SECRET-COMPANY-LLC', 'SECRET-REQUESTER', 'SECRET-GROUP-99',
            ] as $secret) {
                $this->assertStringNotContainsString($secret, $html, "'{$secret}' leaked into the {$name} page");
            }
        }
    }

    // ── status flow over HTTP ─────────────────────────────────────────────

    public function test_detail_offers_the_next_step_as_the_button(): void
    {
        $t = $this->transfer();

        $this->asDriver()->get(route('driver.transfers.show', $t->id))
            ->assertSee(S::EnRouteToClient->getActionLabel())
            ->assertSee('name="from" value="assigned"', false)
            ->assertSee('name="to" value="en_route_to_client"', false);
    }

    public function test_tapping_the_button_advances_the_status_and_logs_it(): void
    {
        $t = $this->transfer();

        $this->asDriver()
            ->post(route('driver.transfers.status', $t->id), ['from' => 'assigned', 'to' => 'en_route_to_client'])
            ->assertRedirect(route('driver.transfers.show', $t->id))
            ->assertSessionHas('driver_notice');

        $this->assertSame(S::EnRouteToClient, $t->fresh()->driver_status);
        $this->assertSame(1, TransferDriverStatusLog::where('transfer_id', $t->id)->count());
    }

    public function test_the_full_trip_ends_on_a_confirm_page_and_completes(): void
    {
        $t = $this->transfer();
        $this->asDriver();

        foreach ([S::EnRouteToClient, S::WaitingForClient, S::OnTheWay] as $to) {
            $this->post(route('driver.transfers.status', $t->id), [
                'from' => $t->fresh()->effectiveDriverStatus()->value,
                'to' => $to->value,
            ])->assertSessionMissing('driver_error');
        }

        // Last step: the button leads to a confirmation page, not straight to the POST.
        $this->get(route('driver.transfers.show', $t->id))
            ->assertSee(route('driver.transfers.complete', $t->id));
        $this->get(route('driver.transfers.complete', $t->id))
            ->assertOk()
            ->assertSee(__('driver.flow.confirm_title'));

        $this->post(route('driver.transfers.status', $t->id), ['from' => 'on_the_way', 'to' => 'completed'])
            ->assertSessionMissing('driver_error');

        $this->assertSame(S::Completed, $t->fresh()->driver_status);
        $this->assertSame(4, TransferDriverStatusLog::where('transfer_id', $t->id)->count());
        $this->get(route('driver.transfers.show', $t->id))->assertSee(__('driver.flow.finished'));
    }

    public function test_the_confirm_page_redirects_away_until_the_last_step_is_next(): void
    {
        $t = $this->transfer();

        $this->asDriver()->get(route('driver.transfers.complete', $t->id))
            ->assertRedirect(route('driver.transfers.show', $t->id));
    }

    public function test_skipping_a_step_is_refused_and_changes_nothing(): void
    {
        $t = $this->transfer();

        $this->asDriver()
            ->post(route('driver.transfers.status', $t->id), ['from' => 'assigned', 'to' => 'on_the_way'])
            ->assertSessionHas('driver_error');

        $this->assertNull($t->fresh()->driver_status);
        $this->assertSame(0, TransferDriverStatusLog::count());
    }

    public function test_a_stale_page_is_refused_with_a_message_not_silently_applied(): void
    {
        $t = $this->transfer();
        $this->asDriver()
            ->post(route('driver.transfers.status', $t->id), ['from' => 'assigned', 'to' => 'en_route_to_client']);

        // A second tab still showing "assigned" tries to advance to waiting_for_client.
        $this->post(route('driver.transfers.status', $t->id), ['from' => 'assigned', 'to' => 'waiting_for_client'])
            ->assertSessionHas('driver_error');

        $this->assertSame(S::EnRouteToClient, $t->fresh()->driver_status);
        $this->assertSame(1, TransferDriverStatusLog::count());
    }

    public function test_a_double_tap_is_success_not_an_error_and_logs_once(): void
    {
        $t = $this->transfer();
        $payload = ['from' => 'assigned', 'to' => 'en_route_to_client'];

        $this->asDriver()->post(route('driver.transfers.status', $t->id), $payload);
        $this->post(route('driver.transfers.status', $t->id), $payload)
            ->assertSessionMissing('driver_error');

        $this->assertSame(1, TransferDriverStatusLog::count());
    }

    public function test_a_transfer_closed_by_the_operator_is_read_only(): void
    {
        $t = $this->transfer(['status' => ExpenseStatus::Done]);

        $this->asDriver()->get(route('driver.transfers.show', $t->id))
            ->assertOk()
            ->assertDontSee('name="to"', false);

        $this->post(route('driver.transfers.status', $t->id), ['from' => 'assigned', 'to' => 'en_route_to_client'])
            ->assertSessionHas('driver_error');

        $this->assertNull($t->fresh()->driver_status);
    }

    public function test_invalid_status_values_are_rejected(): void
    {
        $t = $this->transfer();

        $this->asDriver()
            ->post(route('driver.transfers.status', $t->id), ['from' => 'assigned', 'to' => 'teleported'])
            ->assertSessionHasErrors('to');

        $this->assertNull($t->fresh()->driver_status);
    }

    public function test_a_status_change_over_http_sends_no_telegram_and_keeps_old_values(): void
    {
        $t = $this->transfer(['status' => ExpenseStatus::Confirmed]);
        $snapshot = ['pax' => 2, 'route' => 'last operator edit'];
        $t->forceFill(['old_values' => $snapshot])->saveQuietly();

        Http::fake();

        $this->asDriver()
            ->post(route('driver.transfers.status', $t->id), ['from' => 'assigned', 'to' => 'en_route_to_client'])
            ->assertSessionMissing('driver_error');

        Http::assertNothingSent();
        $this->assertSame($snapshot, $t->fresh()->old_values);
    }

    // ── maps ──────────────────────────────────────────────────────────────

    public function test_the_destination_opens_yandex_maps_with_the_city_prepended(): void
    {
        $city = City::factory()->create(['name' => 'Samarkand']);
        $t = $this->transfer(['route' => 'Registon Plaza', 'to_city_id' => $city->id]);

        $expected = 'https://yandex.uz/maps/?text='.rawurlencode('Samarkand, Registon Plaza');

        $this->asDriver()->get(route('driver.transfers.show', $t->id))->assertSee($expected, false);
        // ...and from the list, via the pin next to the address.
        $this->asDriver()->get(route('driver.transfers'))->assertSee($expected, false);
    }

    public function test_a_transfer_with_no_address_shows_no_map_button(): void
    {
        $t = $this->transfer(['route' => null, 'place_of_submission' => null]);

        $this->asDriver()->get(route('driver.transfers.show', $t->id))
            ->assertOk()
            ->assertDontSee('yandex.uz/maps', false);
    }

    public function test_an_api_created_transfer_maps_the_customers_destination_not_the_a_to_b_description(): void
    {
        $t = $this->transfer([
            'route' => 'Tashkent Airport - Registon Plaza',
            'from' => 'Tashkent Airport',
            'to' => 'Registon Plaza',
        ]);

        $html = $this->asDriver()->get(route('driver.transfers.show', $t->id))->getContent();

        $this->assertStringContainsString('yandex.uz/maps/?text=Registon%20Plaza', $html);
        $this->assertStringNotContainsString('Tashkent%20Airport%20-%20Registon', $html);
    }

    // ── locale ────────────────────────────────────────────────────────────

    public function test_the_language_can_be_switched_before_logging_in(): void
    {
        $this->get(route('driver.login'))->assertSee(__('driver.auth.submit', [], 'ru'));

        $this->from(route('driver.login'))->get(route('driver.locale', 'uz'))->assertRedirect(route('driver.login'));

        $this->get(route('driver.login'))
            ->assertSee(__('driver.auth.submit', [], 'uz'))
            ->assertDontSee(__('driver.auth.submit', [], 'ru'));
    }

    public function test_the_language_choice_is_saved_on_the_driver(): void
    {
        $this->asDriver()->get(route('driver.locale', 'uz'));

        $this->assertSame('uz', $this->driver->fresh()->locale);
    }

    public function test_a_saved_language_applies_on_a_fresh_session(): void
    {
        $this->driver->update(['locale' => 'uz']);
        $this->transfer();

        $this->asDriver()->get(route('driver.transfers'))
            ->assertOk()
            // The Uzbek uses the Latin script, not Carbon's default Cyrillic `uz`.
            ->assertSee('Dushanba')
            ->assertDontSee('душанба');
    }

    public function test_only_ru_and_uz_are_offered(): void
    {
        $this->get(route('driver.locale', 'en'))->assertNotFound();
        $this->get(route('driver.locale', 'de'))->assertNotFound();
    }

    public function test_a_string_missing_from_uzbek_falls_back_to_russian_not_a_raw_key(): void
    {
        app()->setLocale('uz');

        $text = __('validation.email', ['attribute' => 'x']);

        $this->assertStringNotContainsString('validation.email', $text);
    }
}

<?php

namespace Tests\Feature\Driver;

use App\Enums\DriverTransferStatus as S;
use App\Enums\ExpenseStatus;
use App\Models\City;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverStatusLog;
use App\Services\DriverTransferQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DispatcherCabinetTest extends TestCase
{
    use RefreshDatabase;

    private Driver $dispatcher;

    private Driver $rustam;

    private Driver $aziz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-21 10:00:00', 'Asia/Tashkent'));

        $this->dispatcher = Driver::factory()->dispatcher()->create(['name' => 'Dilnoza Dispatcher', 'chat_id' => '111']);
        $this->rustam = Driver::factory()->create([
            'name' => 'Rustam Karimov', 'phone' => '+998901112233', 'car_model' => 'Malibu', 'car_number' => '01 A 123 AA',
        ]);
        $this->aziz = Driver::factory()->create([
            'name' => 'Aziz Yusupov', 'phone' => '+998912223344', 'chat_id' => '987654321',
        ]);
    }

    private function transfer(array $attrs = [], Driver ...$drivers): Transfer
    {
        return Transfer::factory()->forDrivers(...$drivers)->create(array_merge([
            'date_time' => Carbon::parse('2026-09-21 14:30:00', 'Asia/Tashkent'),
        ], $attrs));
    }

    private function asDispatcher(): static
    {
        return $this->actingAs($this->dispatcher, 'driver');
    }

    // ── what a dispatcher sees ────────────────────────────────────────────

    public function test_a_dispatcher_sees_every_drivers_transfers_and_unassigned_ones(): void
    {
        $this->transfer(['route' => 'Rustams Trip'], $this->rustam);
        $this->transfer(['route' => 'Azizs Trip'], $this->aziz);
        $this->transfer(['route' => 'Nobodys Trip']);

        $this->asDispatcher()->get(route('driver.transfers'))
            ->assertOk()
            ->assertSee('Rustams Trip')
            ->assertSee('Azizs Trip')
            ->assertSee('Nobodys Trip');
    }

    public function test_a_dispatcher_sees_only_confirmed_and_done(): void
    {
        $this->transfer(['route' => 'Confirmed Trip', 'status' => ExpenseStatus::Confirmed], $this->rustam);
        $this->transfer(['route' => 'Done Trip', 'status' => ExpenseStatus::Done], $this->rustam);
        $new = $this->transfer(['route' => 'New Trip', 'status' => ExpenseStatus::New], $this->rustam);
        $rejected = $this->transfer(['route' => 'Rejected Trip', 'status' => ExpenseStatus::Rejected]);

        $this->asDispatcher()->get(route('driver.transfers'))
            ->assertSee('Confirmed Trip')
            ->assertSee('Done Trip')
            ->assertDontSee('New Trip')
            ->assertDontSee('Rejected Trip');

        $this->get(route('driver.transfers.show', $new->id))->assertNotFound();
        $this->get(route('driver.transfers.show', $rejected->id))->assertNotFound();
    }

    public function test_the_date_strip_counts_transfers_of_all_drivers(): void
    {
        $this->transfer([], $this->rustam);
        $this->transfer([], $this->aziz);
        $this->transfer([]);

        $html = $this->asDispatcher()->get(route('driver.transfers'))->getContent();
        $chips = preg_match_all('/class="(day [^"]*)"/', $html, $m) ? $m[1] : [];

        // Only today has transfers, so exactly six of the seven chips are empty.
        $this->assertCount(6, array_filter($chips, fn ($c) => str_contains($c, 'day--empty')));
        $this->assertSame(
            3,
            DriverTransferQuery::countPerDay($this->dispatcher, Carbon::parse('2026-09-21'), Carbon::parse('2026-09-21'))['2026-09-21'],
        );
    }

    public function test_the_card_shows_the_driver_a_call_button_and_flags_unassigned_trips(): void
    {
        $assigned = $this->transfer(['route' => 'Assigned Trip'], $this->rustam);
        $this->transfer(['route' => 'Unassigned Trip']);

        $html = $this->asDispatcher()->get(route('driver.transfers'))->assertOk()->getContent();

        $this->assertStringContainsString('Rustam Karimov', $html);
        $this->assertStringContainsString('href="tel:+998901112233"', $html);
        $this->assertSame(1, substr_count($html, __('driver.dispatcher.no_driver')), 'only the unassigned trip is flagged');
        // The call button is a SIBLING of the card link, never nested in it (an <a> inside an <a> is invalid).
        $this->assertDoesNotMatchRegularExpression('/<a class="card__main"[^>]*>(?:(?!<\/a>).)*<a /s', $html);
        $this->assertNotNull($assigned);
    }

    public function test_the_detail_shows_driver_name_car_phone_and_a_call_link(): void
    {
        $t = $this->transfer([], $this->rustam);

        $this->asDispatcher()->get(route('driver.transfers.show', $t->id))
            ->assertOk()
            ->assertSee('Rustam Karimov')
            ->assertSee('Malibu · 01 A 123 AA')
            ->assertSee('+998901112233')
            ->assertSee('href="tel:+998901112233"', false);
    }

    public function test_a_transfer_with_two_drivers_shows_both_on_the_detail_and_a_plus_one_on_the_card(): void
    {
        $t = $this->transfer(['route' => 'Shared Trip'], $this->rustam, $this->aziz);

        $this->asDispatcher()->get(route('driver.transfers.show', $t->id))
            ->assertSee('Rustam Karimov')
            ->assertSee('Aziz Yusupov')
            ->assertSee('+998912223344');

        $this->get(route('driver.transfers'))->assertSee('+1', false);
    }

    public function test_a_free_text_driver_is_shown_when_there_is_no_driver_record(): void
    {
        // Operators can type who is actually driving without picking a Driver record.
        $t = $this->transfer(['driver_name' => 'Taxi Bekzod', 'driver_phone' => '90 555 44 33']);

        $this->asDispatcher()->get(route('driver.transfers.show', $t->id))
            ->assertOk()
            ->assertSee('Taxi Bekzod')
            ->assertSee('tel:+998905554433', false)
            ->assertDontSee(__('driver.dispatcher.no_driver'));
    }

    public function test_a_dispatcher_sees_the_navigation_tabs(): void
    {
        $this->asDispatcher()->get(route('driver.transfers'))
            ->assertSee('class="tabs"', false)
            ->assertSee(route('driver.drivers'));
    }

    // ── prices never reach a dispatcher either ────────────────────────────

    public function test_prices_and_the_client_company_are_never_rendered_for_a_dispatcher(): void
    {
        $company = Company::query()->forceCreate(['name' => 'SECRET-COMPANY-LLC']);
        $t = $this->transfer([
            'company_id' => $company->id,
            'price' => 111111.11,
            'total_price' => 222222.22,
            'sell_price' => 3333333.33,
            'buy_price' => 4444444.44,
            'sell_price_result' => 5555555,
            'buy_price_result' => 6666666,
            'sell_price_currency' => 'USD-SECRET',
            'requested_by' => 'SECRET-REQUESTER',
            'group_number' => 'SECRET-GROUP-99',
        ], $this->rustam);

        $this->asDispatcher();
        $pages = [
            'list' => $this->get(route('driver.transfers'))->assertOk()->getContent(),
            'detail' => $this->get(route('driver.transfers.show', $t->id))->assertOk()->getContent(),
            'drivers' => $this->get(route('driver.drivers'))->assertOk()->getContent(),
        ];

        foreach ($pages as $name => $html) {
            foreach ([
                '111111', '222222', '3333333', '4444444', '5555555', '6666666',
                'USD-SECRET', 'SECRET-COMPANY-LLC', 'SECRET-REQUESTER', 'SECRET-GROUP-99',
            ] as $secret) {
                $this->assertStringNotContainsString($secret, $html, "'{$secret}' leaked into the dispatcher's {$name} page");
            }
        }
    }

    public function test_the_query_never_selects_price_columns_for_a_dispatcher(): void
    {
        $this->transfer(['sell_price' => 999, 'buy_price' => 888], $this->rustam);

        $attributes = DriverTransferQuery::forDriver($this->dispatcher)->firstOrFail()->getAttributes();

        foreach (['price', 'total_price', 'sell_price', 'buy_price', 'sell_price_result', 'buy_price_result', 'old_values', 'company_id'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $attributes);
        }
    }

    public function test_driver_lookup_reads_contact_columns_only_never_credentials(): void
    {
        $t = $this->transfer([], $this->aziz);

        $loaded = DriverTransferQuery::driversFor([$t])->get($this->aziz->id);

        $this->assertSame('Aziz Yusupov', $loaded->name);
        foreach (['password', 'chat_id', 'phone_normalized', 'remember_token'] as $secret) {
            $this->assertArrayNotHasKey($secret, $loaded->getAttributes());
        }
    }

    // ── a plain driver gains nothing ──────────────────────────────────────

    public function test_a_plain_driver_still_sees_only_their_own_transfers(): void
    {
        $this->transfer(['route' => 'Rustams Trip'], $this->rustam);
        $this->transfer(['route' => 'Azizs Trip'], $this->aziz);
        $this->transfer(['route' => 'Nobodys Trip']);

        $this->actingAs($this->rustam, 'driver')->get(route('driver.transfers'))
            ->assertSee('Rustams Trip')
            ->assertDontSee('Azizs Trip')
            ->assertDontSee('Nobodys Trip');
    }

    public function test_a_plain_driver_never_sees_a_codrivers_contact_details(): void
    {
        $t = $this->transfer(['route' => 'Shared Trip'], $this->rustam, $this->aziz);

        $html = $this->actingAs($this->rustam, 'driver')
            ->get(route('driver.transfers.show', $t->id))->assertOk()->getContent();
        $list = $this->get(route('driver.transfers'))->getContent();

        foreach ([$html, $list] as $page) {
            $this->assertStringNotContainsString('Aziz Yusupov', $page);
            $this->assertStringNotContainsString('+998912223344', $page);
            $this->assertStringNotContainsString('class="tabs"', $page);
            $this->assertStringNotContainsString(__('driver.dispatcher.no_driver'), $page);
        }
    }

    public function test_a_plain_driver_gets_a_404_on_the_drivers_page(): void
    {
        $this->actingAs($this->rustam, 'driver')->get(route('driver.drivers'))->assertNotFound();
    }

    public function test_a_guest_is_sent_to_login_from_the_drivers_page(): void
    {
        $this->get(route('driver.drivers'))->assertRedirect(route('driver.login'));
    }

    public function test_a_plain_driver_is_still_limited_to_one_step_forward(): void
    {
        $t = $this->transfer([], $this->rustam);

        $this->actingAs($this->rustam, 'driver')
            ->post(route('driver.transfers.status', $t->id), ['from' => 'assigned', 'to' => 'completed'])
            ->assertSessionHas('driver_error');

        $this->assertNull($t->fresh()->driver_status);
    }

    // ── the Drivers page ──────────────────────────────────────────────────

    public function test_the_drivers_page_lists_drivers_with_contact_details_and_todays_trips(): void
    {
        $this->transfer([], $this->rustam);
        $this->transfer([], $this->rustam, $this->aziz);   // counts once for EACH of its two drivers

        $html = $this->asDispatcher()->get(route('driver.drivers'))->assertOk()->getContent();

        $this->assertStringContainsString('Rustam Karimov', $html);
        $this->assertStringContainsString('Malibu · 01 A 123 AA', $html);
        $this->assertStringContainsString('href="tel:+998901112233"', $html);
        $this->assertStringContainsString(__('driver.dispatcher.trips_today', ['count' => 2]), $html);
        $this->assertStringContainsString(__('driver.dispatcher.trips_today', ['count' => 1]), $html);
    }

    public function test_the_drivers_page_does_not_list_dispatchers_and_marks_disabled_drivers(): void
    {
        Driver::factory()->inactive()->create(['name' => 'Sleepy Driver']);

        $html = $this->asDispatcher()->get(route('driver.drivers'))->getContent();

        $this->assertStringNotContainsString('Dilnoza Dispatcher', $html, 'dispatchers are staff, not vehicles');
        $this->assertStringContainsString('Sleepy Driver', $html);
        $this->assertStringContainsString(__('driver.dispatcher.disabled'), $html);
    }

    public function test_the_drivers_page_never_exposes_credentials_or_telegram_ids(): void
    {
        $html = $this->asDispatcher()->get(route('driver.drivers'))->getContent();

        $this->assertStringNotContainsString('987654321', $html, 'chat_id');
        $this->assertStringNotContainsString($this->aziz->getRawOriginal('password'), $html);
        $this->assertStringNotContainsString('$2y$', $html, 'no bcrypt hash anywhere');
    }

    public function test_trips_today_are_counted_from_string_driver_ids(): void
    {
        // Pin the production storage format: ["7","9"], raw, not the model cast's round-trip.
        DB::table('transfers')->insert([
            'driver_ids' => '["'.$this->rustam->id.'","'.$this->aziz->id.'"]',
            'status' => ExpenseStatus::Confirmed->value,
            'date_time' => '2026-09-21 12:00:00',
        ]);

        $counts = DriverTransferQuery::tripsPerDriverOn($this->dispatcher, Carbon::parse('2026-09-21', 'Asia/Tashkent'));

        $this->assertSame(1, $counts[$this->rustam->id]);
        $this->assertSame(1, $counts[$this->aziz->id]);
    }

    // ── changing status ───────────────────────────────────────────────────

    public function test_a_dispatcher_can_jump_straight_to_any_status_and_back(): void
    {
        $t = $this->transfer([], $this->rustam);
        $this->asDispatcher();

        // assigned -> completed skips three steps: a driver could not do this.
        $this->post(route('driver.transfers.status', $t->id), ['to' => 'completed'])
            ->assertSessionMissing('driver_error')
            ->assertSessionHas('driver_notice');
        $this->assertSame(S::Completed, $t->fresh()->driver_status);

        // ...and backwards, which a driver also cannot.
        $this->post(route('driver.transfers.status', $t->id), ['to' => 'waiting_for_client'])
            ->assertSessionMissing('driver_error');
        $this->assertSame(S::WaitingForClient, $t->fresh()->driver_status);
    }

    public function test_every_dispatcher_change_is_logged_under_their_name(): void
    {
        $t = $this->transfer([], $this->rustam);

        $this->asDispatcher()->post(route('driver.transfers.status', $t->id), ['to' => 'on_the_way']);

        $log = TransferDriverStatusLog::where('transfer_id', $t->id)->sole();
        $this->assertSame('dispatcher', $log->source);
        $this->assertSame($this->dispatcher->id, $log->driver_id);
        $this->assertSame(S::Assigned, $log->from_status);
        $this->assertSame(S::OnTheWay, $log->to_status);
    }

    public function test_the_detail_offers_a_status_picker_with_the_current_status_selected(): void
    {
        $t = $this->transfer([], $this->rustam);
        $this->asDispatcher()->post(route('driver.transfers.status', $t->id), ['to' => 'waiting_for_client']);

        $html = $this->get(route('driver.transfers.show', $t->id))->assertOk()->getContent();

        $this->assertStringContainsString('<select id="to" name="to">', $html);
        $this->assertSame(5, substr_count($html, '<option value='), 'all five statuses are offered');
        $this->assertMatchesRegularExpression('/<option value="waiting_for_client"\s+selected/', $html);
        $this->assertStringNotContainsString('name="from"', $html, 'the driver-only stale-page guard is not used here');
    }

    public function test_picking_the_current_status_again_is_a_harmless_no_op(): void
    {
        $t = $this->transfer([], $this->rustam);
        $this->asDispatcher();

        $this->post(route('driver.transfers.status', $t->id), ['to' => 'on_the_way']);
        $this->post(route('driver.transfers.status', $t->id), ['to' => 'on_the_way'])
            ->assertSessionMissing('driver_error');

        $this->assertSame(1, TransferDriverStatusLog::count());
    }

    public function test_a_dispatcher_can_change_a_transfer_that_has_no_driver_yet(): void
    {
        $t = $this->transfer([]);

        $this->asDispatcher()->post(route('driver.transfers.status', $t->id), ['to' => 'en_route_to_client'])
            ->assertSessionMissing('driver_error');

        $this->assertSame(S::EnRouteToClient, $t->fresh()->driver_status);
    }

    public function test_a_transfer_closed_by_the_operator_stays_read_only_for_a_dispatcher(): void
    {
        $t = $this->transfer(['status' => ExpenseStatus::Done], $this->rustam);

        $this->asDispatcher()->get(route('driver.transfers.show', $t->id))
            ->assertOk()
            ->assertDontSee('<select id="to"', false);

        $this->post(route('driver.transfers.status', $t->id), ['to' => 'completed'])
            ->assertSessionHas('driver_error');

        $this->assertNull($t->fresh()->driver_status);
    }

    public function test_an_invalid_status_value_is_rejected(): void
    {
        $t = $this->transfer([], $this->rustam);

        $this->asDispatcher()->post(route('driver.transfers.status', $t->id), ['to' => 'teleported'])
            ->assertSessionHasErrors('to');

        $this->assertNull($t->fresh()->driver_status);
    }

    public function test_a_dispatcher_status_change_sends_no_telegram_and_keeps_old_values(): void
    {
        $t = $this->transfer(['status' => ExpenseStatus::Confirmed], $this->aziz);   // aziz has a chat_id
        $snapshot = ['pax' => 2, 'route' => 'last operator edit'];
        $t->forceFill(['old_values' => $snapshot])->saveQuietly();

        Http::fake();

        $this->asDispatcher()->post(route('driver.transfers.status', $t->id), ['to' => 'completed'])
            ->assertSessionMissing('driver_error');

        Http::assertNothingSent();
        $this->assertSame($snapshot, $t->fresh()->old_values);
    }

    public function test_the_complete_confirmation_page_is_not_used_by_a_dispatcher(): void
    {
        $t = $this->transfer([], $this->rustam);
        $this->asDispatcher()->post(route('driver.transfers.status', $t->id), ['to' => 'on_the_way']);

        // For a driver this URL is the last-step confirmation; a dispatcher just goes back to the picker.
        $this->get(route('driver.transfers.complete', $t->id))->assertRedirect(route('driver.transfers.show', $t->id));
    }

    // ── language ──────────────────────────────────────────────────────────

    public function test_dispatcher_screens_are_translated_to_uzbek(): void
    {
        $this->dispatcher->update(['locale' => 'uz']);
        $this->transfer([]);

        $this->asDispatcher()->get(route('driver.transfers'))
            ->assertSee('Haydovchilar')
            ->assertSee('Haydovchisiz');
    }

    public function test_the_map_link_still_works_for_a_dispatcher(): void
    {
        $city = City::factory()->create(['name' => 'Samarkand']);
        $t = $this->transfer(['route' => 'Registon Plaza', 'to_city_id' => $city->id], $this->rustam);

        $this->asDispatcher()->get(route('driver.transfers.show', $t->id))
            ->assertSee('https://yandex.uz/maps/?text='.rawurlencode('Samarkand, Registon Plaza'), false);
    }
}

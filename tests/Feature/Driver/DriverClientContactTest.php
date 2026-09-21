<?php

namespace Tests\Feature\Driver;

use App\Enums\ExpenseStatus;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Transfer;
use App\Services\DriverTransferQuery;
use App\Support\DriverTransferPresenter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverClientContactTest extends TestCase
{
    use RefreshDatabase;

    private Driver $driver;

    private Driver $other;

    private Driver $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-21 10:00:00', 'Asia/Tashkent'));

        $this->driver = Driver::factory()->create();
        $this->other = Driver::factory()->create();
        $this->dispatcher = Driver::factory()->dispatcher()->create();
    }

    private function trip(array $attrs = []): Transfer
    {
        return Transfer::factory()->forDrivers($this->driver)->create(array_merge([
            'date_time' => Carbon::parse('2026-09-21 14:30:00', 'Asia/Tashkent'),
        ], $attrs));
    }

    private function detail(Transfer $t, ?Driver $as = null): string
    {
        return $this->actingAs($as ?? $this->driver, 'driver')
            ->get(route('driver.transfers.show', $t->id))->assertOk()->getContent();
    }

    private function list(?Driver $as = null): string
    {
        return $this->actingAs($as ?? $this->driver, 'driver')
            ->get(route('driver.transfers'))->assertOk()->getContent();
    }

    // ── the pickup sign and the comment ───────────────────────────────────

    public function test_the_detail_page_puts_the_sign_and_the_comment_in_a_prominent_block(): void
    {
        $t = $this->trip(['nameplate' => 'MR JOHN SMITH', 'comment' => 'Child seat needed']);

        $html = $this->detail($t);

        $this->assertStringContainsString('class="callout"', $html);
        $this->assertMatchesRegularExpression('#class="callout__sign">MR JOHN SMITH</div>#', $html);
        $this->assertMatchesRegularExpression('#class="callout__text">Child seat needed</div>#', $html);
    }

    public function test_the_sign_and_comment_are_not_repeated_in_the_ordinary_field_list(): void
    {
        $t = $this->trip(['nameplate' => 'MR JOHN SMITH', 'comment' => 'Child seat needed']);

        $html = $this->detail($t);

        $this->assertSame(1, substr_count($html, 'MR JOHN SMITH'));
        $this->assertSame(1, substr_count($html, 'Child seat needed'));
    }

    public function test_without_a_sign_or_comment_there_is_no_empty_block(): void
    {
        $t = $this->trip(['nameplate' => null, 'comment' => null]);

        $this->assertStringNotContainsString('class="callout"', $this->detail($t));
    }

    public function test_dash_placeholders_typed_by_operators_count_as_empty(): void
    {
        // Operators type "-" for "nothing" in several free-text fields.
        $t = $this->trip(['nameplate' => '-', 'comment' => '-']);

        $this->assertStringNotContainsString('class="callout"', $this->detail($t));
    }

    public function test_only_the_field_that_exists_is_shown(): void
    {
        // Both trips first: once a request acts as a driver, the default guard is `driver` and
        // Transfer::creating() would stamp a driver's id into created_by (a foreign key to users).
        $signOnly = $this->trip(['nameplate' => 'MS ANNA K', 'comment' => null]);
        $noteOnly = $this->trip(['nameplate' => null, 'comment' => 'Two large suitcases']);

        $html = $this->detail($signOnly);
        $this->assertStringContainsString('class="callout__sign"', $html);
        $this->assertStringNotContainsString('class="callout__text"', $html);

        $html = $this->detail($noteOnly);
        $this->assertStringNotContainsString('class="callout__sign"', $html);
        $this->assertStringContainsString('class="callout__text"', $html);
    }

    public function test_the_list_card_shows_the_sign_and_a_preview_of_the_comment(): void
    {
        $this->trip(['route' => 'Registon Plaza', 'nameplate' => 'MR JOHN SMITH', 'comment' => "Child seat needed\nCall from the gate"]);

        $html = $this->list();

        $this->assertMatchesRegularExpression('#class="card__sign"><span class="card__k">[^<]+</span>MR JOHN SMITH</span>#', $html);
        $this->assertStringContainsString('Child seat needed', $html);
    }

    public function test_a_card_with_no_sign_or_comment_has_neither_line(): void
    {
        $this->trip(['route' => 'Plain Trip', 'nameplate' => null, 'comment' => null]);

        $html = $this->list();

        $this->assertStringNotContainsString('class="card__sign"', $html);
        $this->assertStringNotContainsString('class="card__note"', $html);
    }

    public function test_the_sign_and_comment_are_escaped_everywhere(): void
    {
        // Free text from operators and, via the website, from customers.
        $t = $this->trip([
            'nameplate' => '"><img src=x onerror=alert(1)>',
            'comment' => '<script>alert("comment")</script>',
        ]);

        foreach ([$this->detail($t), $this->list()] as $html) {
            $this->assertStringNotContainsString('<script>alert("comment")', $html);
            $this->assertStringNotContainsString('<img src=x onerror', $html);
            $this->assertStringContainsString('&lt;script&gt;', $html);
        }
    }

    public function test_the_list_link_and_its_buttons_are_siblings_never_nested(): void
    {
        $this->trip(['nameplate' => 'MR SMITH', 'comment' => 'A note', 'client_phone' => '+447911123456']);

        // An <a> inside the card's main <a> is invalid HTML and unpredictable on touch screens.
        $this->assertDoesNotMatchRegularExpression('/<a class="card__main"[^>]*>(?:(?!<\/a>).)*<a /s', $this->list());
    }

    // ── the client block and its buttons ──────────────────────────────────

    public function test_the_client_block_gives_call_whatsapp_and_telegram_links(): void
    {
        $t = $this->trip(['passenger' => 'Jane Smith', 'client_phone' => '+44 7911 123456']);

        $html = $this->detail($t);

        $this->assertStringContainsString('Jane Smith', $html);
        $this->assertStringContainsString('href="tel:+447911123456"', $html);
        $this->assertStringContainsString('href="https://wa.me/447911123456"', $html);
        $this->assertStringContainsString('href="https://t.me/+447911123456"', $html);
        // The messenger links leave the app; they must not hand the cabinet page to the other site.
        $this->assertMatchesRegularExpression('#href="https://wa\.me/447911123456" target="_blank" rel="noopener"#', $html);
        $this->assertMatchesRegularExpression('#href="https://t\.me/\+447911123456" target="_blank" rel="noopener"#', $html);
    }

    public function test_an_uzbek_local_number_gets_all_three_buttons(): void
    {
        $t = $this->trip(['client_phone' => '90 123 45 67']);

        $html = $this->detail($t);

        $this->assertStringContainsString('href="tel:+998901234567"', $html);
        $this->assertStringContainsString('href="https://wa.me/998901234567"', $html);
        $this->assertStringContainsString('href="https://t.me/+998901234567"', $html);
    }

    public function test_a_number_without_a_country_gets_only_a_call_button(): void
    {
        $t = $this->trip(['client_phone' => '(555) 123-4567']);

        $html = $this->detail($t);

        $this->assertStringContainsString('href="tel:5551234567"', $html);
        $this->assertStringNotContainsString('wa.me', $html);
        $this->assertStringNotContainsString('t.me', $html);
    }

    public function test_a_foreign_nine_digit_number_is_never_turned_into_a_plus_998_link(): void
    {
        $t = $this->trip(['client_phone' => '612 345 678']);

        $html = $this->detail($t);

        $this->assertStringNotContainsString('998612345678', $html);
        $this->assertStringNotContainsString('wa.me', $html);
    }

    public function test_the_name_shows_even_when_there_is_no_phone_and_no_buttons_are_drawn(): void
    {
        $t = $this->trip(['passenger' => 'Jane Smith', 'client_phone' => null]);

        $html = $this->detail($t);

        $this->assertStringContainsString('Jane Smith', $html);
        $this->assertStringNotContainsString('class="contact"', $html);
    }

    public function test_with_neither_a_name_nor_a_phone_there_is_no_client_block(): void
    {
        $t = $this->trip(['passenger' => null, 'client_phone' => null]);

        $this->assertStringNotContainsString('class="client"', $this->detail($t));
    }

    public function test_a_phone_that_is_not_a_phone_number_is_not_shown(): void
    {
        $t = $this->trip(['client_phone' => 'call reception']);

        $html = $this->detail($t);

        $this->assertStringNotContainsString('class="contact"', $html);
        $this->assertStringNotContainsString('call reception', $html);
    }

    public function test_the_buttons_are_labelled_for_screen_readers_and_carry_the_brand_icons(): void
    {
        $t = $this->trip(['client_phone' => '+447911123456']);

        $html = $this->detail($t);

        $this->assertStringContainsString('<use href="#i-whatsapp"/>', $html);
        $this->assertStringContainsString('<use href="#i-telegram"/>', $html);
        $this->assertStringContainsString('<symbol id="i-whatsapp"', $html);
        $this->assertStringContainsString('<symbol id="i-telegram"', $html);
        $this->assertStringContainsString('<span>WhatsApp</span>', $html);
        $this->assertStringContainsString('<span>Telegram</span>', $html);
    }

    // ── privacy: who gets the number, and when ────────────────────────────

    public function test_a_driver_sees_the_number_while_the_trip_is_open(): void
    {
        $t = $this->trip(['status' => ExpenseStatus::Confirmed, 'client_phone' => '+447911123456']);

        $this->assertStringContainsString('447911123456', $this->detail($t));
    }

    public function test_once_the_operator_closes_the_trip_the_driver_no_longer_gets_the_number_anywhere_in_the_page(): void
    {
        $t = $this->trip(['status' => ExpenseStatus::Done, 'passenger' => 'Jane Smith', 'client_phone' => '+447911123456']);

        $html = $this->detail($t);

        foreach (['447911123456', 'wa.me', 't.me', 'tel:', 'class="contact"'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "'{$needle}' must not be in a closed trip's page");
        }
        $this->assertStringContainsString('Jane Smith', $html, 'the name stays');
    }

    public function test_a_dispatcher_always_gets_the_number_including_on_a_closed_trip(): void
    {
        $open = $this->trip(['status' => ExpenseStatus::Confirmed, 'client_phone' => '+447911123456']);
        $closed = $this->trip(['status' => ExpenseStatus::Done, 'client_phone' => '+447911999888']);

        $this->assertStringContainsString('href="https://wa.me/447911123456"', $this->detail($open, $this->dispatcher));
        $this->assertStringContainsString('href="https://wa.me/447911999888"', $this->detail($closed, $this->dispatcher));
    }

    public function test_the_number_never_appears_in_the_list_for_anyone(): void
    {
        $this->trip(['client_phone' => '+447911123456', 'passenger' => 'Jane Smith']);

        foreach ([$this->list(), $this->list($this->dispatcher)] as $html) {
            $this->assertStringNotContainsString('447911123456', $html);
            $this->assertStringNotContainsString('wa.me', $html);
        }
    }

    public function test_another_driver_cannot_reach_the_trip_or_its_number(): void
    {
        $t = $this->trip(['client_phone' => '+447911123456']);

        $this->actingAs($this->other, 'driver')
            ->get(route('driver.transfers.show', $t->id))->assertNotFound();
    }

    public function test_the_phone_is_only_exposed_when_the_caller_explicitly_asks(): void
    {
        $t = Transfer::factory()->create(['client_phone' => '+447911123456']);

        // Safe by default: a call site that forgets the argument leaks nothing.
        $this->assertNull((new DriverTransferPresenter($t))->clientContact);
        $this->assertNull((new DriverTransferPresenter($t, null, false))->clientContact);
        $this->assertSame('+447911123456', (new DriverTransferPresenter($t, null, true))->clientContact['tel']);
    }

    // ── the whitelist and money still hold ────────────────────────────────

    public function test_the_phone_is_selected_for_the_cabinet_but_no_price_column_is(): void
    {
        $this->trip(['client_phone' => '+447911123456', 'sell_price' => 999, 'buy_price' => 888]);

        $attributes = DriverTransferQuery::forDriver($this->driver)->firstOrFail()->getAttributes();

        $this->assertArrayHasKey('client_phone', $attributes);
        foreach (['price', 'total_price', 'sell_price', 'buy_price', 'old_values', 'company_id'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $attributes);
        }
    }

    public function test_prices_and_the_client_company_still_never_reach_the_page(): void
    {
        $company = Company::query()->forceCreate(['name' => 'SECRET-COMPANY-LLC']);
        $t = $this->trip([
            'company_id' => $company->id, 'sell_price' => 3333333.33, 'buy_price' => 4444444.44,
            'client_phone' => '+447911123456', 'nameplate' => 'MR SMITH', 'comment' => 'A note',
        ]);

        foreach ([$this->detail($t), $this->list(), $this->detail($t, $this->dispatcher)] as $html) {
            $this->assertStringNotContainsString('3333333', $html);
            $this->assertStringNotContainsString('4444444', $html);
            $this->assertStringNotContainsString('SECRET-COMPANY-LLC', $html);
        }
    }

    public function test_the_labels_are_translated_to_uzbek(): void
    {
        $this->driver->update(['locale' => 'uz']);
        $t = $this->trip(['passenger' => 'Jane Smith', 'client_phone' => '+447911123456', 'nameplate' => 'MR SMITH']);

        $html = $this->detail($t);

        $this->assertStringContainsString('Mijoz', $html);
        $this->assertStringContainsString('Tablichka', $html);
    }
}

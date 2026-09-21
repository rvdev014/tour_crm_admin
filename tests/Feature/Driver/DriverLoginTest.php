<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DriverLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-123';

    private function driver(array $attrs = []): Driver
    {
        return Driver::factory()->create(array_merge([
            'phone' => '+998901112233',
            'password' => self::PASSWORD,
        ], $attrs));
    }

    private function attempt(string $phone, string $password = self::PASSWORD)
    {
        return $this->from(route('driver.login'))->post(route('driver.login.attempt'), [
            'phone' => $phone,
            'password' => $password,
        ]);
    }

    public function test_guests_are_sent_to_the_driver_login_not_the_api_login(): void
    {
        // Regression: the stock `auth` middleware targets route('login'), a POST-only API endpoint (405).
        $this->get('/driver/transfers')->assertRedirect(route('driver.login'));
        $this->get('/driver')->assertRedirect(route('driver.login'));
        $this->get('/driver/transfers/1')->assertRedirect(route('driver.login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get(route('driver.login'))
            ->assertOk()
            ->assertSee('name="phone"', false)
            ->assertSee('name="password"', false);
    }

    /** @dataProvider phoneFormats */
    public function test_the_same_driver_can_log_in_however_the_phone_is_typed(string $typed): void
    {
        $driver = $this->driver();

        $this->attempt($typed)->assertRedirect(route('driver.transfers'));

        $this->assertAuthenticatedAs($driver, 'driver');
        $this->assertNotNull($driver->fresh()->last_login_at);
    }

    public static function phoneFormats(): array
    {
        return [
            'e164' => ['+998901112233'],
            'no plus' => ['998901112233'],
            'national spaced' => ['90 111 22 33'],
            'international spaced' => ['+998 90 111 22 33'],
            'punctuated' => ['(90) 111-22-33'],
        ];
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->driver();

        $this->attempt('+998901112233', 'nope')->assertSessionHasErrors('phone');
        $this->assertGuest('driver');
    }

    public function test_an_unknown_and_an_unusable_phone_get_the_same_message_as_a_wrong_password(): void
    {
        $this->driver();

        $wrongPassword = $this->attempt('+998901112233', 'nope')->getSession()->get('errors')->first('phone');
        $unknown = $this->attempt('+998911119999')->getSession()->get('errors')->first('phone');
        $unusable = $this->attempt('12345')->getSession()->get('errors')->first('phone');

        $this->assertNotEmpty($wrongPassword);
        $this->assertSame($wrongPassword, $unknown, 'must not reveal which numbers exist');
        $this->assertSame($wrongPassword, $unusable);
    }

    public function test_a_driver_with_no_password_fails_cleanly_instead_of_erroring(): void
    {
        // Regression: Hash::check() throws on a NULL hash. Right after the migration every existing
        // driver is in this state, so this must be an ordinary "wrong credentials", never a 500.
        $this->driver(['password' => null]);

        $this->attempt('+998901112233', 'anything')
            ->assertSessionHasErrors('phone')
            ->assertRedirect(route('driver.login'));
        $this->assertGuest('driver');
    }

    public function test_an_inactive_driver_cannot_log_in(): void
    {
        $this->driver(['is_active' => false]);

        $this->attempt('+998901112233')->assertSessionHasErrors('phone');
        $this->assertGuest('driver');
    }

    public function test_a_logged_in_driver_is_kept_off_the_login_page(): void
    {
        $this->actingAs($this->driver(), 'driver')
            ->get(route('driver.login'))
            ->assertRedirect(route('driver.transfers'));
    }

    public function test_login_is_rate_limited_per_phone_and_ip(): void
    {
        RateLimiter::clear('driver-login');
        $this->driver();

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('+998901112233', 'wrong')->assertSessionHasErrors('phone');
        }

        // The 6th attempt is blocked — even with the CORRECT password.
        $this->attempt('+998901112233')->assertStatus(429);
        $this->assertGuest('driver');
    }

    public function test_logout_ends_the_driver_session_but_not_a_crm_operator_session_in_the_same_browser(): void
    {
        $operator = User::factory()->create();

        $this->actingAs($operator, 'web')
            ->actingAs($this->driver(), 'driver')
            ->post(route('driver.logout'))
            ->assertRedirect(route('driver.login'));

        $this->assertGuest('driver');
        $this->assertAuthenticatedAs($operator, 'web');
    }

    public function test_a_driver_session_does_not_open_the_crm(): void
    {
        $this->actingAs($this->driver(), 'driver')
            ->get('/admin')
            ->assertRedirect();

        $this->assertGuest('web');
    }

    public function test_a_crm_session_does_not_open_the_cabinet(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->get('/driver/transfers')
            ->assertRedirect(route('driver.login'));
    }

    public function test_cabinet_pages_are_never_cached(): void
    {
        $this->get(route('driver.login'))
            ->assertHeader('Cache-Control');

        $this->assertStringContainsString(
            'no-store',
            $this->get(route('driver.login'))->headers->get('Cache-Control'),
        );
    }
}

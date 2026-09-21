<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverAuthModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_normalized_is_derived_on_every_save(): void
    {
        $driver = Driver::factory()->create(['phone' => '90 111 22 33']);
        $this->assertSame('+998901112233', $driver->fresh()->phone_normalized);

        $driver->update(['phone' => '+998 91 222 33 44']);
        $this->assertSame('+998912223344', $driver->fresh()->phone_normalized);
    }

    public function test_an_unusable_phone_leaves_phone_normalized_null(): void
    {
        $driver = Driver::factory()->create(['phone' => '998299221']);

        $this->assertNull($driver->fresh()->phone_normalized);
    }

    public function test_the_same_number_in_two_formats_cannot_belong_to_two_drivers(): void
    {
        Driver::factory()->create(['phone' => '+998917786638']);

        $this->expectException(QueryException::class);
        Driver::factory()->create(['phone' => '91 778 66 38']);
    }

    public function test_many_drivers_without_a_usable_phone_can_coexist(): void
    {
        // The unique index is partial: NULLs must not collide.
        Driver::factory()->count(2)->create(['phone' => null]);

        $this->assertSame(2, Driver::whereNull('phone_normalized')->count());
    }

    public function test_password_is_stored_hashed(): void
    {
        $driver = Driver::factory()->create(['password' => 'secret-123']);

        $stored = $driver->getRawOriginal('password');
        $this->assertNotSame('secret-123', $stored);
        $this->assertTrue(Hash::check('secret-123', $stored));
    }

    public function test_driver_guard_authenticates_by_normalized_phone(): void
    {
        Driver::factory()->create(['phone' => '90 111 22 33', 'password' => 'secret-123']);

        $this->assertTrue(Auth::guard('driver')->attempt([
            'phone_normalized' => '+998901112233',
            'password' => 'secret-123',
        ]));
        $this->assertNotNull(Auth::guard('driver')->user());
    }

    public function test_a_driver_without_a_password_can_never_log_in(): void
    {
        Driver::factory()->withoutCabinet()->create(['phone' => '90 111 22 33']);

        $this->assertFalse(Auth::guard('driver')->attempt([
            'phone_normalized' => '+998901112233',
            'password' => '',
        ]));
        $this->assertFalse(Auth::guard('driver')->attempt([
            'phone_normalized' => '+998901112233',
            'password' => 'anything',
        ]));
    }

    public function test_driver_and_web_guards_are_isolated(): void
    {
        $driver = Driver::factory()->create();

        Auth::guard('driver')->login($driver);

        $this->assertTrue(Auth::guard('driver')->check());
        $this->assertFalse(Auth::guard('web')->check(), 'a driver session must not authenticate the CRM guard');

        Auth::guard('driver')->logout();
        Auth::guard('web')->login(User::factory()->create());

        $this->assertTrue(Auth::guard('web')->check());
        $this->assertFalse(Auth::guard('driver')->check(), 'a CRM session must not authenticate the driver guard');
    }
}

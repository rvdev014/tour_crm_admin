<?php

namespace Tests\Feature\Driver;

use App\Filament\Resources\DriverResource\Pages\CreateDriver;
use App\Filament\Resources\DriverResource\Pages\EditDriver;
use App\Filament\Resources\DriverResource\Pages\ListDrivers;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDriverResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 0]));
    }

    public function test_an_operator_can_create_a_driver_with_cabinet_access(): void
    {
        Livewire::test(CreateDriver::class)
            ->fillForm([
                'name' => 'Rustam',
                'phone' => '+998901112233',
                'car_number' => '01 A 123 AA',
                'car_model' => 'Chevrolet Malibu',
                'password' => 'driver123',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $driver = Driver::where('name', 'Rustam')->firstOrFail();
        $this->assertSame('+998901112233', $driver->phone_normalized);
        $this->assertSame('01 A 123 AA', $driver->car_number);
        $this->assertNotSame('driver123', $driver->getRawOriginal('password'), 'must be stored hashed');
        $this->assertTrue(Hash::check('driver123', $driver->getRawOriginal('password')));

        // ...and it actually works end to end, typed the way a driver would type it.
        $this->assertTrue(Auth::guard('driver')->attempt([
            'phone_normalized' => '+998901112233',
            'password' => 'driver123',
        ]));
    }

    public function test_a_driver_can_be_created_without_a_password(): void
    {
        Livewire::test(CreateDriver::class)
            ->fillForm(['name' => 'No Cabinet', 'phone' => '+998901112233'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Driver::where('name', 'No Cabinet')->firstOrFail()->password);
    }

    public function test_the_same_number_in_another_spelling_is_rejected_as_a_form_error(): void
    {
        Driver::factory()->create(['phone' => '+998917786638']);

        // Without the normalized-uniqueness rule this reaches the DB's unique index and 500s.
        Livewire::test(CreateDriver::class)
            ->fillForm(['name' => 'Duplicate', 'phone' => '91 778 66 38'])
            ->call('create')
            ->assertHasFormErrors(['phone']);

        $this->assertSame(0, Driver::where('name', 'Duplicate')->count());
    }

    public function test_editing_a_driver_may_keep_their_own_phone(): void
    {
        $driver = Driver::factory()->create(['phone' => '+998917786638']);

        Livewire::test(EditDriver::class, ['record' => $driver->id])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed', $driver->fresh()->name);
    }

    public function test_leaving_the_password_blank_keeps_the_current_one(): void
    {
        $driver = Driver::factory()->create(['password' => 'keep-me-123']);
        $hashBefore = $driver->getRawOriginal('password');

        Livewire::test(EditDriver::class, ['record' => $driver->id])
            ->fillForm(['name' => 'Renamed', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $driver->fresh();
        $this->assertSame($hashBefore, $fresh->getRawOriginal('password'));
        $this->assertTrue(Hash::check('keep-me-123', $fresh->getRawOriginal('password')));
    }

    public function test_the_stored_hash_is_never_put_into_the_edit_form(): void
    {
        $driver = Driver::factory()->create(['password' => 'keep-me-123']);

        Livewire::test(EditDriver::class, ['record' => $driver->id])
            ->assertFormSet(['password' => null]);
    }

    public function test_a_new_password_replaces_the_old_one(): void
    {
        $driver = Driver::factory()->create(['password' => 'old-password']);

        Livewire::test(EditDriver::class, ['record' => $driver->id])
            ->fillForm(['password' => 'brand-new-1'])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = $driver->fresh()->getRawOriginal('password');
        $this->assertTrue(Hash::check('brand-new-1', $stored));
        $this->assertFalse(Hash::check('old-password', $stored));
    }

    public function test_a_too_short_password_is_rejected(): void
    {
        $driver = Driver::factory()->create();

        Livewire::test(EditDriver::class, ['record' => $driver->id])
            ->fillForm(['password' => '123'])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_the_list_shows_who_can_log_in(): void
    {
        $can = Driver::factory()->create();
        $noPassword = Driver::factory()->withoutCabinet()->create();
        $disabled = Driver::factory()->inactive()->create();

        Livewire::test(ListDrivers::class)
            ->assertTableColumnStateSet('cabinet', true, $can)
            ->assertTableColumnStateSet('cabinet', false, $noPassword)
            ->assertTableColumnStateSet('cabinet', false, $disabled);
    }

    public function test_generate_password_makes_a_working_login_and_shows_it_once(): void
    {
        $driver = Driver::factory()->create(['phone' => '+998901112233', 'password' => 'old-password']);

        Livewire::test(ListDrivers::class)
            ->callTableAction('generate_password', $driver)
            ->assertHasNoTableActionErrors();

        // The plaintext exists only in the flashed notification.
        $flashed = collect(session('filament.notifications', []))->pluck('body')->filter()->implode(' ');
        $this->assertMatchesRegularExpression('/\+998901112233 — (\w{8})/u', $flashed);
        preg_match('/\+998901112233 — (\w{8})/u', $flashed, $m);

        $this->assertTrue(Hash::check($m[1], $driver->fresh()->getRawOriginal('password')));
        $this->assertFalse(Hash::check('old-password', $driver->fresh()->getRawOriginal('password')));
        $this->assertTrue(Auth::guard('driver')->attempt([
            'phone_normalized' => '+998901112233',
            'password' => $m[1],
        ]));

        // Only the hash is persisted: the plaintext appears nowhere in the driver's row.
        $this->assertStringNotContainsString($m[1], json_encode(DB::table('drivers')->where('id', $driver->id)->first()));
    }

    public function test_generate_password_refuses_when_the_phone_is_unusable(): void
    {
        // 998299221 is a real production row (id 3): a truncated number the normalizer rejects.
        $driver = Driver::factory()->create(['phone' => '998299221', 'password' => null]);

        Livewire::test(ListDrivers::class)->callTableAction('generate_password', $driver);

        $this->assertNull($driver->fresh()->password);
    }
}

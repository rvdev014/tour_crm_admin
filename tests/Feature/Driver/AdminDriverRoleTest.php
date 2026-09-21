<?php

namespace Tests\Feature\Driver;

use App\Enums\DriverRole;
use App\Enums\DriverTransferStatus as S;
use App\Filament\Resources\DriverResource\Pages\CreateDriver;
use App\Filament\Resources\DriverResource\Pages\EditDriver;
use App\Filament\Resources\DriverResource\Pages\ListDrivers;
use App\Filament\Resources\TransferResource\Pages\EditTransfer;
use App\Filament\Resources\TransferResource\RelationManagers\DriverStatusLogsRelationManager;
use App\Models\Country;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\User;
use App\Services\CacheService;
use App\Services\DriverTransferStatusService;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDriverRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CacheService::$cache = [];
        $this->actingAs(User::factory()->create(['role' => 0]));   // Admin
    }

    // ── create / edit ─────────────────────────────────────────────────────

    public function test_an_operator_can_create_a_dispatcher_who_can_then_log_in(): void
    {
        Livewire::test(CreateDriver::class)
            ->fillForm([
                'name' => 'Dilnoza',
                'phone' => '+998901112233',
                'role' => DriverRole::Dispatcher->value,
                'password' => 'dispatch123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = Driver::where('name', 'Dilnoza')->firstOrFail();
        $this->assertSame(DriverRole::Dispatcher, $created->role);

        $this->assertTrue(Auth::guard('driver')->attempt([
            'phone_normalized' => '+998901112233',
            'password' => 'dispatch123',
        ]));
        $this->assertTrue(Auth::guard('driver')->user()->isDispatcher());
    }

    public function test_the_role_defaults_to_driver(): void
    {
        Livewire::test(CreateDriver::class)
            ->fillForm(['name' => 'Rustam', 'phone' => '+998901112233'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(DriverRole::Driver, Driver::where('name', 'Rustam')->firstOrFail()->role);
    }

    public function test_the_role_is_required(): void
    {
        Livewire::test(CreateDriver::class)
            ->fillForm(['name' => 'X', 'role' => null])
            ->call('create')
            ->assertHasFormErrors(['role' => 'required']);
    }

    // ── the guard on turning a working driver into a dispatcher ───────────

    public function test_a_driver_with_upcoming_trips_cannot_become_a_dispatcher(): void
    {
        $driver = Driver::factory()->create();
        Transfer::factory()->forDrivers($driver)->create(['date_time' => Carbon::now('Asia/Tashkent')->addDay()]);
        Transfer::factory()->forDrivers($driver)->create(['date_time' => Carbon::now('Asia/Tashkent')->addDays(3)]);

        Livewire::test(EditDriver::class, ['record' => $driver->id])
            ->fillForm(['role' => DriverRole::Dispatcher->value])
            ->call('save')
            ->assertHasFormErrors(['role']);

        $this->assertSame(DriverRole::Driver, $driver->fresh()->role);
    }

    public function test_the_guard_counts_todays_trips_as_upcoming(): void
    {
        $driver = Driver::factory()->create();
        Transfer::factory()->forDrivers($driver)->create(['date_time' => Carbon::now('Asia/Tashkent')->startOfDay()->addHours(20)]);

        Livewire::test(EditDriver::class, ['record' => $driver->id])
            ->fillForm(['role' => DriverRole::Dispatcher->value])
            ->call('save')
            ->assertHasFormErrors(['role']);
    }

    public function test_a_driver_with_only_past_trips_can_become_a_dispatcher(): void
    {
        $driver = Driver::factory()->create();
        Transfer::factory()->forDrivers($driver)->create(['date_time' => Carbon::now('Asia/Tashkent')->subDays(5)]);

        Livewire::test(EditDriver::class, ['record' => $driver->id])
            ->fillForm(['role' => DriverRole::Dispatcher->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(DriverRole::Dispatcher, $driver->fresh()->role);
    }

    public function test_another_drivers_upcoming_trips_do_not_block_the_change(): void
    {
        [$leaving, $other] = Driver::factory()->count(2)->create();
        Transfer::factory()->forDrivers($other)->create(['date_time' => Carbon::now('Asia/Tashkent')->addDay()]);

        Livewire::test(EditDriver::class, ['record' => $leaving->id])
            ->fillForm(['role' => DriverRole::Dispatcher->value])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_a_dispatcher_can_be_edited_and_switched_back_to_a_driver(): void
    {
        $dispatcher = Driver::factory()->dispatcher()->create();
        // Even with a stray upcoming assignment, editing an existing dispatcher is not blocked.
        Transfer::factory()->forDrivers($dispatcher)->create(['date_time' => Carbon::now('Asia/Tashkent')->addDay()]);

        Livewire::test(EditDriver::class, ['record' => $dispatcher->id])
            ->fillForm(['name' => 'Renamed', 'role' => DriverRole::Dispatcher->value])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditDriver::class, ['record' => $dispatcher->id])
            ->fillForm(['role' => DriverRole::Driver->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(DriverRole::Driver, $dispatcher->fresh()->role);
    }

    // ── list ──────────────────────────────────────────────────────────────

    public function test_the_list_shows_the_role_and_can_filter_by_it(): void
    {
        $driver = Driver::factory()->create();
        $dispatcher = Driver::factory()->dispatcher()->create();

        Livewire::test(ListDrivers::class)
            ->assertCanSeeTableRecords([$driver, $dispatcher])
            ->assertSee(DriverRole::Dispatcher->getLabel())
            ->filterTable('role', DriverRole::Dispatcher->value)
            ->assertCanSeeTableRecords([$dispatcher])
            ->assertCanNotSeeTableRecords([$driver]);
    }

    // ── dispatchers are not assignable, and their changes are attributed ──

    public function test_the_driver_supplier_picker_does_not_offer_dispatchers(): void
    {
        Country::create(['name' => 'Uzbekistan']);   // the transfer form's city options look this up
        $driver = Driver::factory()->create(['name' => 'Rustam']);
        $dispatcher = Driver::factory()->dispatcher()->create(['name' => 'Dilnoza']);
        $transfer = Transfer::factory()->forDrivers($driver)->create();

        Livewire::test(EditTransfer::class, ['record' => $transfer->id])
            ->assertFormFieldExists('driver_ids', function (Select $field) use ($driver, $dispatcher) {
                $options = $field->getOptions();

                return array_key_exists($driver->id, $options) && ! array_key_exists($dispatcher->id, $options);
            });
    }

    public function test_the_history_tab_attributes_a_dispatcher_change_to_the_dispatcher(): void
    {
        $dispatcher = Driver::factory()->dispatcher()->create(['name' => 'Dilnoza']);
        $transfer = Transfer::factory()->forDrivers(Driver::factory()->create())->create();
        DriverTransferStatusService::apply($transfer, S::Completed, $dispatcher, 'dispatcher');

        Livewire::test(DriverStatusLogsRelationManager::class, [
            'ownerRecord' => $transfer,
            'pageClass' => EditTransfer::class,
        ])
            ->assertCanSeeTableRecords($transfer->driverStatusLogs)
            ->assertSee('Диспетчер: Dilnoza');
    }
}

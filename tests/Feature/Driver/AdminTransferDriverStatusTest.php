<?php

namespace Tests\Feature\Driver;

use App\Enums\DriverTransferStatus as S;
use App\Filament\Resources\TransferResource\Pages\EditTransfer;
use App\Filament\Resources\TransferResource\Pages\ListTransfers;
use App\Filament\Resources\TransferResource\RelationManagers\DriverStatusLogsRelationManager;
use App\Models\Country;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverStatusLog;
use App\Models\User;
use App\Services\DriverTransferStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTransferDriverStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // role 0 = Admin: sees every transfer (non-admins only see the ones they created).
        $this->actingAs(User::factory()->create(['role' => 0]));

        // The transfer form's city options look up a country named "Uzbekistan" (TourService::getCities()).
        Country::create(['name' => 'Uzbekistan']);
    }

    private function filter(array $data)
    {
        return Livewire::test(ListTransfers::class)->filterTable('today', array_merge([
            'today' => false, 'tomorrow' => false, 'driver_ids' => null, 'companies' => null,
            'statuses' => null, 'driver_statuses' => null, 'date_from' => null, 'date_until' => null,
        ], $data));
    }

    // ── list column ───────────────────────────────────────────────────────

    public function test_the_list_shows_the_status_the_driver_reported(): void
    {
        $driver = Driver::factory()->create();
        $transfer = Transfer::factory()->forDrivers($driver)->create();
        DriverTransferStatusService::apply($transfer, S::EnRouteToClient, $driver);

        Livewire::test(ListTransfers::class)
            ->assertCanSeeTableRecords([$transfer])
            ->assertTableColumnFormattedStateSet('driver_status', S::EnRouteToClient->getLabel(), $transfer)
            ->assertSee(S::EnRouteToClient->getLabel());
    }

    public function test_an_untouched_transfer_with_a_driver_reads_as_assigned(): void
    {
        $transfer = Transfer::factory()->forDrivers(Driver::factory()->create())->create();

        Livewire::test(ListTransfers::class)
            ->assertTableColumnFormattedStateSet('driver_status', S::Assigned->getLabel(), $transfer);
    }

    public function test_a_transfer_with_no_driver_shows_no_driver_status(): void
    {
        $transfer = Transfer::factory()->create(['driver_ids' => []]);

        Livewire::test(ListTransfers::class)
            ->assertCanSeeTableRecords([$transfer])
            ->assertTableColumnStateSet('driver_status', null, $transfer);
    }

    // ── filter ────────────────────────────────────────────────────────────

    public function test_filtering_by_assigned_includes_rows_the_driver_has_not_touched_yet(): void
    {
        $driver = Driver::factory()->create();
        $untouched = Transfer::factory()->forDrivers($driver)->create();          // NULL column
        $moving = Transfer::factory()->forDrivers($driver)->create();
        DriverTransferStatusService::apply($moving, S::EnRouteToClient, $driver);
        $noDriver = Transfer::factory()->create(['driver_ids' => []]);

        $this->filter(['driver_statuses' => [S::Assigned->value]])
            ->assertCanSeeTableRecords([$untouched])
            ->assertCanNotSeeTableRecords([$moving, $noDriver]);
    }

    public function test_filtering_by_a_later_status_matches_only_that_status(): void
    {
        $driver = Driver::factory()->create();
        $untouched = Transfer::factory()->forDrivers($driver)->create();
        $moving = Transfer::factory()->forDrivers($driver)->create();
        DriverTransferStatusService::apply($moving, S::EnRouteToClient, $driver);

        $this->filter(['driver_statuses' => [S::EnRouteToClient->value]])
            ->assertCanSeeTableRecords([$moving])
            ->assertCanNotSeeTableRecords([$untouched]);
    }

    public function test_filtering_by_several_drivers_means_any_of_them_not_all(): void
    {
        // Regression: the filter passed the whole array to one whereJsonContains (`@> '["a","b"]'`),
        // i.e. "assigned to ALL of them", so picking two drivers only matched shared transfers.
        [$a, $b, $c] = Driver::factory()->count(3)->create();
        $onlyA = Transfer::factory()->forDrivers($a)->create();
        $onlyB = Transfer::factory()->forDrivers($b)->create();
        $both = Transfer::factory()->forDrivers($a, $b)->create();
        $onlyC = Transfer::factory()->forDrivers($c)->create();

        $this->filter(['driver_ids' => [(string) $a->id, (string) $b->id]])
            ->assertCanSeeTableRecords([$onlyA, $onlyB, $both])
            ->assertCanNotSeeTableRecords([$onlyC]);
    }

    public function test_filtering_by_one_driver_still_works(): void
    {
        [$a, $b] = Driver::factory()->count(2)->create();
        $mine = Transfer::factory()->forDrivers($a)->create();
        $other = Transfer::factory()->forDrivers($b)->create();

        $this->filter(['driver_ids' => [(string) $a->id]])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // ── history tab + override action ─────────────────────────────────────

    public function test_the_history_tab_lists_every_status_change(): void
    {
        $driver = Driver::factory()->create();
        $transfer = Transfer::factory()->forDrivers($driver)->create();
        DriverTransferStatusService::apply($transfer, S::EnRouteToClient, $driver);
        DriverTransferStatusService::apply($transfer, S::WaitingForClient, $driver);

        Livewire::test(DriverStatusLogsRelationManager::class, [
            'ownerRecord' => $transfer,
            'pageClass' => EditTransfer::class,
        ])->assertCanSeeTableRecords($transfer->driverStatusLogs);
    }

    public function test_an_operator_can_set_any_driver_status_and_it_is_logged_as_admin(): void
    {
        $driver = Driver::factory()->create();
        $transfer = Transfer::factory()->forDrivers($driver)->create();
        DriverTransferStatusService::apply($transfer, S::EnRouteToClient, $driver);
        DriverTransferStatusService::apply($transfer, S::WaitingForClient, $driver);

        Livewire::test(EditTransfer::class, ['record' => $transfer->id])
            ->callAction('set_driver_status', ['status' => S::Assigned->value])
            ->assertHasNoActionErrors();

        $this->assertSame(S::Assigned, $transfer->fresh()->driver_status);

        $last = TransferDriverStatusLog::latest('id')->first();
        $this->assertSame('admin', $last->source);
        $this->assertNull($last->driver_id);
        $this->assertSame(S::WaitingForClient, $last->from_status);
        $this->assertSame(S::Assigned, $last->to_status);
    }

    public function test_the_override_action_sends_no_telegram_message(): void
    {
        $driver = Driver::factory()->create(['chat_id' => '123456789']);
        $transfer = Transfer::factory()->forDrivers($driver)->create();

        Http::fake();

        Livewire::test(EditTransfer::class, ['record' => $transfer->id])
            ->callAction('set_driver_status', ['status' => S::OnTheWay->value]);

        Http::assertNothingSent();
    }

    public function test_the_override_action_is_hidden_when_no_driver_is_assigned(): void
    {
        $transfer = Transfer::factory()->create(['driver_ids' => []]);

        Livewire::test(EditTransfer::class, ['record' => $transfer->id])
            ->assertActionHidden('set_driver_status');
    }

    public function test_the_edit_form_shows_the_current_driver_status(): void
    {
        $driver = Driver::factory()->create();
        $transfer = Transfer::factory()->forDrivers($driver)->create();
        DriverTransferStatusService::apply($transfer, S::OnTheWay, $driver, 'admin');

        Livewire::test(EditTransfer::class, ['record' => $transfer->id])
            ->assertSee(S::OnTheWay->getLabel());
    }
}

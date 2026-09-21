<?php

namespace Tests\Feature\Driver;

use App\Enums\DriverTransferStatus as S;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverStatusLog;
use App\Services\DriverTransferQuery;
use App\Services\DriverTransferStatusService as Service;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DriverTransferStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private Driver $driver;

    private Transfer $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = Driver::factory()->create(['chat_id' => '123456789']);
        $this->transfer = Transfer::factory()->forDrivers($this->driver)->create();
    }

    public function test_untouched_transfer_reads_as_assigned(): void
    {
        $this->assertNull($this->transfer->fresh()->driver_status);
        $this->assertSame(S::Assigned, $this->transfer->fresh()->effectiveDriverStatus());
    }

    public function test_full_walk_writes_one_log_row_per_step(): void
    {
        foreach ([S::EnRouteToClient, S::WaitingForClient, S::OnTheWay, S::Completed] as $to) {
            $this->assertTrue(Service::apply($this->transfer, $to, $this->driver));
        }

        $fresh = $this->transfer->fresh();
        $this->assertSame(S::Completed, $fresh->driver_status);
        $this->assertNotNull($fresh->driver_status_updated_at);

        $logs = $fresh->driverStatusLogs()->orderBy('id')->get();
        $this->assertCount(4, $logs);
        $this->assertSame(
            [
                [S::Assigned, S::EnRouteToClient],
                [S::EnRouteToClient, S::WaitingForClient],
                [S::WaitingForClient, S::OnTheWay],
                [S::OnTheWay, S::Completed],
            ],
            $logs->map(fn ($l) => [$l->from_status, $l->to_status])->all(),
        );
        $this->assertSame([$this->driver->id], $logs->pluck('driver_id')->unique()->all());
        $this->assertSame(['driver'], $logs->pluck('source')->unique()->all());
    }

    public function test_driver_cannot_skip_a_status(): void
    {
        try {
            Service::apply($this->transfer, S::OnTheWay, $this->driver);
            $this->fail('Expected a DomainException for assigned -> on_the_way');
        } catch (DomainException) {
            // expected
        }

        $this->assertNull($this->transfer->fresh()->driver_status);
        $this->assertSame(0, TransferDriverStatusLog::count());
    }

    public function test_driver_cannot_move_backwards(): void
    {
        Service::apply($this->transfer, S::EnRouteToClient, $this->driver);

        $this->expectException(DomainException::class);
        Service::apply($this->transfer, S::Assigned, $this->driver);
    }

    public function test_repeating_the_same_status_is_a_harmless_no_op(): void
    {
        $this->assertTrue(Service::apply($this->transfer, S::EnRouteToClient, $this->driver));
        $this->assertFalse(Service::apply($this->transfer, S::EnRouteToClient, $this->driver));

        $this->assertSame(1, TransferDriverStatusLog::count());
    }

    public function test_an_admin_may_correct_a_mis_tap_by_setting_any_status(): void
    {
        Service::apply($this->transfer, S::EnRouteToClient, $this->driver);
        Service::apply($this->transfer, S::WaitingForClient, $this->driver);

        $this->assertTrue(Service::apply($this->transfer, S::Assigned, null, TransferDriverStatusLog::SOURCE_ADMIN));

        $this->assertSame(S::Assigned, $this->transfer->fresh()->driver_status);
        $last = TransferDriverStatusLog::orderByDesc('id')->first();
        $this->assertSame('admin', $last->source);
        $this->assertNull($last->driver_id);
        $this->assertSame(S::WaitingForClient, $last->from_status);
    }

    public function test_works_on_the_price_free_model_the_cabinet_actually_loads(): void
    {
        $partial = DriverTransferQuery::forDriver($this->driver)->firstOrFail();
        $this->assertArrayNotHasKey('sell_price', $partial->getAttributes());

        Service::apply($partial, S::EnRouteToClient, $this->driver);

        $this->assertSame(S::EnRouteToClient, $this->transfer->fresh()->driver_status);
        // ...and the caller's model was not silently widened with price columns.
        $this->assertArrayNotHasKey('sell_price', $partial->getAttributes());
    }

    /**
     * Control for the test below. Without this, "no Telegram sent" could pass simply because this
     * setup never produces a Telegram message at all.
     */
    public function test_control_a_normal_operator_edit_of_a_confirmed_transfer_does_message_the_driver(): void
    {
        Http::fake();

        // Reload first: a freshly create()d model lacks the unset columns (to_city_id, ...) that the
        // Telegram message builder reads from toArray(). Models loaded from the DB, as in the CRM, have them.
        Transfer::findOrFail($this->transfer->id)->update(['pax' => 5]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org')
            && ($request['chat_id'] ?? null) === '123456789'
            && str_contains($request['text'], 'Обновлено'));
    }

    public function test_a_driver_status_change_sends_no_telegram_and_leaves_old_values_alone(): void
    {
        $snapshot = ['pax' => 2, 'route' => 'Snapshot of the last operator edit'];
        $this->transfer->forceFill(['old_values' => $snapshot])->saveQuietly();

        Http::fake();

        Service::apply($this->transfer, S::EnRouteToClient, $this->driver);

        Http::assertNothingSent();
        $this->assertSame($snapshot, $this->transfer->fresh()->old_values);
    }
}

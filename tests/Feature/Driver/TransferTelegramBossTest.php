<?php

namespace Tests\Feature\Driver;

use App\Enums\ExpenseStatus;
use App\Models\Driver;
use App\Models\Transfer;
use App\Services\TourService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TourService::sendTelegramTransfer() is the single function behind every driver-facing transfer
 * Telegram message (create, update, reminder — see CreateTransfer::afterCreate(),
 * TransferObserver::updated(), TourService::notifyDrivers()). The "transfer boss" (chat_id
 * 2254596, hardcoded in TRANSFER_BOSS_CHAT_ID) is meant to get a copy of all of them, so these
 * tests go through that one function/its real callers rather than re-asserting driver delivery
 * (already covered by DriverTransferStatusServiceTest).
 */
class TransferTelegramBossTest extends TestCase
{
    use RefreshDatabase;

    private const BOSS_CHAT_ID = '2254596';

    public function test_creating_a_confirmed_transfer_sends_a_copy_to_the_boss(): void
    {
        $driver = Driver::factory()->create(['chat_id' => '123456789']);
        $transfer = Transfer::factory()->forDrivers($driver)->create(['status' => ExpenseStatus::Confirmed]);

        Http::fake();

        // What CreateTransfer::afterCreate() does for a Confirmed transfer.
        TourService::sendTelegramTransfer(Transfer::findOrFail($transfer->id)->toArray());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org')
            && ($request['chat_id'] ?? null) === '123456789');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org')
            && ($request['chat_id'] ?? null) === self::BOSS_CHAT_ID);
    }

    public function test_a_transfer_with_no_driver_assigned_still_notifies_the_boss(): void
    {
        $transfer = Transfer::factory()->create(['status' => ExpenseStatus::Confirmed, 'driver_ids' => []]);

        Http::fake();

        TourService::sendTelegramTransfer(Transfer::findOrFail($transfer->id)->toArray());

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => ($request['chat_id'] ?? null) === self::BOSS_CHAT_ID);
    }

    public function test_updating_a_confirmed_transfer_messages_the_boss_too(): void
    {
        $driver = Driver::factory()->create(['chat_id' => '123456789']);
        $transfer = Transfer::factory()->forDrivers($driver)->create(['status' => ExpenseStatus::Confirmed]);

        Http::fake();

        // Reload first: a freshly create()d model lacks columns the message builder reads from
        // toArray() (see DriverTransferStatusServiceTest's control test for the same gotcha).
        Transfer::findOrFail($transfer->id)->update(['pax' => 5]);

        Http::assertSent(fn ($request) => ($request['chat_id'] ?? null) === '123456789'
            && str_contains($request['text'], 'Обновлено'));
        Http::assertSent(fn ($request) => ($request['chat_id'] ?? null) === self::BOSS_CHAT_ID
            && str_contains($request['text'], 'Обновлено'));
    }

    public function test_a_non_confirmed_transfer_update_notifies_nobody_boss_included(): void
    {
        $driver = Driver::factory()->create(['chat_id' => '123456789']);
        $transfer = Transfer::factory()->forDrivers($driver)->create(['status' => ExpenseStatus::New]);

        Http::fake();

        Transfer::findOrFail($transfer->id)->update(['pax' => 5]);

        Http::assertNothingSent();
    }

    public function test_the_60_30_minute_reminder_messages_the_boss(): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00', 'Asia/Tashkent'));

        $driver = Driver::factory()->create(['chat_id' => '123456789']);
        Transfer::factory()->forDrivers($driver)->create([
            'status' => ExpenseStatus::Confirmed,
            'date_time' => Carbon::parse('2026-09-21 10:45:00', 'Asia/Tashkent'),
            'notified_times' => 0,
        ]);

        Http::fake();

        app(TourService::class)->notifyDrivers();

        Http::assertSent(fn ($request) => ($request['chat_id'] ?? null) === '123456789'
            && str_contains($request['text'], 'НАПОМИНАНИЕ'));
        Http::assertSent(fn ($request) => ($request['chat_id'] ?? null) === self::BOSS_CHAT_ID
            && str_contains($request['text'], 'НАПОМИНАНИЕ'));
    }
}

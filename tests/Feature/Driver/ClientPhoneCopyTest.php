<?php

namespace Tests\Feature\Driver;

use App\Models\Transfer;
use App\Models\TransferBooking;
use App\Models\TransferRequest;
use App\Services\TransferQuoteService;
use App\Services\TransferService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientPhoneCopyTest extends TestCase
{
    use RefreshDatabase;

    private function request(array $attrs = []): TransferRequest
    {
        return TransferRequest::create(array_merge([
            'date_time' => Carbon::parse('2026-09-21 14:30:00'),
            'passengers_count' => 2,
            'from' => 'Tashkent International Airport',
            'to' => 'Registon Plaza',
            'fio' => 'Jane Smith',
            'phone' => '+44 7911 123456',
        ], $attrs));
    }

    public function test_accepting_a_website_request_copies_the_customers_phone_onto_the_transfer(): void
    {
        $transfer = TransferService::acceptRequest($this->request());

        $this->assertSame('+44 7911 123456', $transfer->fresh()->client_phone);
        $this->assertSame('Jane Smith', $transfer->fresh()->passenger);
    }

    public function test_a_request_without_a_phone_leaves_it_empty(): void
    {
        $transfer = TransferService::acceptRequest($this->request(['phone' => null]));

        $this->assertNull($transfer->fresh()->client_phone);
    }

    public function test_accepting_a_booking_copies_the_phone_to_every_vehicle(): void
    {
        $booking = TransferBooking::create([
            'first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com',
            'phone' => '+44 7911 123456', 'hotel_address' => 'Hotel X',
        ]);
        $this->request(['transfer_booking_id' => $booking->id, 'vehicle_count' => 2]);

        $transfers = (new TransferQuoteService)->acceptBooking($booking->fresh());

        $this->assertCount(2, $transfers, 'one Transfer per physical vehicle');
        foreach ($transfers as $transfer) {
            $this->assertSame('+44 7911 123456', $transfer->fresh()->client_phone);
        }
    }

    public function test_the_backfill_fills_transfers_that_existed_before_the_column_and_touches_nothing_else(): void
    {
        $request = $this->request(['phone' => '+7 916 123-45-67']);
        $orphan = Transfer::factory()->create(['transfer_request_id' => $request->id, 'client_phone' => null]);
        $unrelated = Transfer::factory()->create(['client_phone' => null]);
        $alreadySet = Transfer::factory()->create(['transfer_request_id' => $this->request(['phone' => null])->id, 'client_phone' => 'typed by an operator']);

        // The statement from the migration, verbatim.
        DB::statement(<<<'SQL'
            UPDATE transfers t
            SET client_phone = tr.phone
            FROM transfer_requests tr
            WHERE t.transfer_request_id = tr.id
              AND tr.phone IS NOT NULL
              AND tr.phone <> ''
        SQL);

        $this->assertSame('+7 916 123-45-67', $orphan->fresh()->client_phone);
        $this->assertNull($unrelated->fresh()->client_phone);
        $this->assertSame('typed by an operator', $alreadySet->fresh()->client_phone, 'a request with no phone must not blank what an operator typed');
    }
}

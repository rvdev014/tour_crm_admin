<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferBooking;
use App\Models\TransferRequest;
use App\Services\TransferQuoteService;
use App\Services\TransferService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransferCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '41.31627274, 69.25246429';   // real production format: lat, lng (Tashkent)

    private const TO = '41.26183701, 69.26720428';

    private function request(array $attrs = []): TransferRequest
    {
        return TransferRequest::create(array_merge([
            'date_time' => Carbon::parse('2026-09-21 14:30:00'),
            'passengers_count' => 2,
            'from' => 'Tashkent International Airport',
            'to' => 'Registon Plaza',
            'from_coords' => self::FROM,
            'to_coords' => self::TO,
        ], $attrs));
    }

    public function test_accepting_a_request_copies_its_coordinates_onto_the_transfer(): void
    {
        $transfer = TransferService::acceptRequest($this->request());

        $this->assertSame(self::FROM, $transfer->fresh()->from_coords);
        $this->assertSame(self::TO, $transfer->fresh()->to_coords);
    }

    public function test_accepting_a_request_without_coordinates_leaves_them_null(): void
    {
        $transfer = TransferService::acceptRequest($this->request(['from_coords' => null, 'to_coords' => null]));

        $this->assertNull($transfer->fresh()->from_coords);
        $this->assertNull($transfer->fresh()->to_coords);
    }

    public function test_accepting_a_booking_copies_each_legs_coordinates_to_every_vehicle(): void
    {
        $booking = TransferBooking::create([
            'first_name' => 'Ann', 'last_name' => 'Smith', 'email' => 'ann@example.com',
            'phone' => '+998901112233', 'hotel_address' => 'Hotel X',
        ]);
        $this->request([
            'transfer_booking_id' => $booking->id,
            'vehicle_count' => 2,
        ]);

        $transfers = (new TransferQuoteService)->acceptBooking($booking->fresh());

        $this->assertCount(2, $transfers, 'one Transfer per physical vehicle');
        foreach ($transfers as $transfer) {
            $this->assertSame(self::FROM, $transfer->fresh()->from_coords);
            $this->assertSame(self::TO, $transfer->fresh()->to_coords);
        }
    }

    public function test_the_backfill_sql_fills_transfers_created_before_the_columns_existed(): void
    {
        $request = $this->request();
        $orphan = Transfer::factory()->create(['transfer_request_id' => $request->id, 'from_coords' => null, 'to_coords' => null]);
        $unrelated = Transfer::factory()->create(['from_coords' => null, 'to_coords' => null]);

        // The statement from the migration, verbatim.
        DB::statement(<<<'SQL'
            UPDATE transfers t
            SET from_coords = tr.from_coords,
                to_coords = tr.to_coords
            FROM transfer_requests tr
            WHERE t.transfer_request_id = tr.id
              AND (tr.from_coords IS NOT NULL OR tr.to_coords IS NOT NULL)
        SQL);

        $this->assertSame(self::FROM, $orphan->fresh()->from_coords);
        $this->assertNull($unrelated->fresh()->from_coords);
    }

    // ── the cabinet uses them ─────────────────────────────────────────────

    public function test_the_cabinet_opens_an_exact_pin_when_the_transfer_has_coordinates(): void
    {
        $driver = Driver::factory()->create();
        $transfer = TransferService::acceptRequest($this->request());
        $transfer->update(['driver_ids' => [(string) $driver->id], 'status' => \App\Enums\ExpenseStatus::Confirmed]);

        $html = $this->actingAs($driver, 'driver')
            ->get(route('driver.transfers.show', $transfer->id))
            ->assertOk()
            ->getContent();

        // destination pin (lon,lat — reversed from storage) ...
        $this->assertStringContainsString('ll=69.267204,41.261837', $html);
        // ...and pickup pin, because the pickup is the customer's own `from`.
        $this->assertStringContainsString('ll=69.252464,41.316273', $html);
        $this->assertStringNotContainsString('maps/?text=', $html);
    }

    public function test_an_operator_typed_pickup_keeps_the_text_search_not_the_customers_coordinates(): void
    {
        $driver = Driver::factory()->create();
        $transfer = TransferService::acceptRequest($this->request());
        $transfer->update([
            'driver_ids' => [(string) $driver->id],
            'status' => \App\Enums\ExpenseStatus::Confirmed,
            'place_of_submission' => 'Meeting point: Gate 3',
        ]);

        $html = $this->actingAs($driver, 'driver')
            ->get(route('driver.transfers.show', $transfer->id))
            ->getContent();

        // The coordinates describe the customer's original `from`; they must not be attached to a
        // pickup the operator has since replaced.
        $this->assertStringContainsString('text='.rawurlencode('Meeting point: Gate 3'), $html);
        $this->assertStringNotContainsString('ll=69.252464,41.316273', $html);
        // The destination is unchanged, so its pin still applies.
        $this->assertStringContainsString('ll=69.267204,41.261837', $html);
    }
}

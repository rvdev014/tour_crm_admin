<?php

namespace Tests\Feature\Driver;

use App\Enums\ExpenseStatus;
use App\Models\Driver;
use App\Models\Transfer;
use App\Services\DriverTransferQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Runs against real Postgres on purpose: the behaviour under test is `driver_ids::jsonb @> ?`,
 * which sqlite would happily get wrong.
 */
class DriverTransferQueryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Insert with a RAW driver_ids string, bypassing the model cast, so the test pins the format that
     * is actually stored in production rather than whatever the cast round-trips to.
     */
    private function insertTransfer(?string $rawDriverIds, ExpenseStatus $status = ExpenseStatus::Confirmed): int
    {
        return DB::table('transfers')->insertGetId([
            'driver_ids' => $rawDriverIds,
            'status' => $status->value,
            'date_time' => now(),
            'route' => 'Registon Plaza',
            'sell_price' => 1234567.89,
            'buy_price' => 987654.32,
        ]);
    }

    private function idsFor(Driver $driver): array
    {
        return DriverTransferQuery::forDriver($driver)->pluck('id')->all();
    }

    public function test_matches_ids_stored_as_strings_the_production_format(): void
    {
        $driver = Driver::factory()->create();
        $id = $this->insertTransfer('["'.$driver->id.'"]');

        $this->assertSame([$id], $this->idsFor($driver));
    }

    public function test_matches_ids_stored_as_bare_ints(): void
    {
        $driver = Driver::factory()->create();
        $id = $this->insertTransfer('['.$driver->id.']');

        $this->assertSame([$id], $this->idsFor($driver));
    }

    public function test_matches_when_several_drivers_share_a_transfer(): void
    {
        [$a, $b] = Driver::factory()->count(2)->create();
        $id = $this->insertTransfer('["'.$a->id.'","'.$b->id.'"]');

        $this->assertSame([$id], $this->idsFor($a));
        $this->assertSame([$id], $this->idsFor($b));
    }

    public function test_does_not_return_another_drivers_transfers(): void
    {
        [$mine, $other] = Driver::factory()->count(2)->create();
        $this->insertTransfer('["'.$other->id.'"]');

        $this->assertSame([], $this->idsFor($mine));
    }

    public function test_id_match_is_exact_not_a_prefix(): void
    {
        $driver = Driver::factory()->create();
        // e.g. driver 7 must not see a transfer for driver 70 or 17.
        $this->insertTransfer('["'.$driver->id.'0"]');
        $this->insertTransfer('["1'.$driver->id.'"]');

        $this->assertSame([], $this->idsFor($driver));
    }

    public function test_transfers_without_drivers_do_not_break_the_query(): void
    {
        $driver = Driver::factory()->create();
        $this->insertTransfer(null);
        $this->insertTransfer('[]');

        $this->assertSame([], $this->idsFor($driver));
    }

    public function test_only_confirmed_and_done_are_visible(): void
    {
        $driver = Driver::factory()->create();
        $raw = '["'.$driver->id.'"]';

        $confirmed = $this->insertTransfer($raw, ExpenseStatus::Confirmed);
        $done = $this->insertTransfer($raw, ExpenseStatus::Done);
        $this->insertTransfer($raw, ExpenseStatus::New);
        $this->insertTransfer($raw, ExpenseStatus::Rejected);

        $this->assertEqualsCanonicalizing([$confirmed, $done], $this->idsFor($driver));
    }

    public function test_price_columns_are_never_fetched(): void
    {
        $driver = Driver::factory()->create();
        $this->insertTransfer('["'.$driver->id.'"]');

        $attributes = DriverTransferQuery::forDriver($driver)->firstOrFail()->getAttributes();

        foreach ([
            'price', 'total_price', 'sell_price', 'sell_price_result', 'sell_price_currency',
            'buy_price', 'buy_price_result', 'buy_price_currency', 'old_values', 'send_username', 'company_id',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $attributes, "{$forbidden} must not be selected for drivers");
        }

        $this->assertArrayHasKey('route', $attributes);
    }

    public function test_the_factory_relation_round_trips_through_the_query(): void
    {
        $driver = Driver::factory()->create();
        $transfer = Transfer::factory()->forDrivers($driver)->create();

        $this->assertSame([$transfer->id], $this->idsFor($driver));
    }
}

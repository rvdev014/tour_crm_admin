<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Models\Driver;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ExpenseStatus::Confirmed,
            'date_time' => now()->setTime(14, 0),
            'route' => 'Registon Plaza',
            'place_of_submission' => 'Samarkand Airport, terminal 2',
            'pax' => 2,
            'driver_ids' => [],
        ];
    }

    /**
     * Assign drivers the way the CRM does: a JSON array of ids stored as STRINGS (["7"]).
     * Goes through the model cast, so use DB::table() when a test must pin the raw stored format.
     */
    public function forDrivers(Driver ...$drivers): static
    {
        return $this->state(fn () => [
            'driver_ids' => array_map(fn (Driver $d) => (string) $d->getKey(), $drivers),
        ]);
    }
}

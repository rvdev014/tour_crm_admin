<?php

namespace Database\Factories;

use App\Enums\DriverExpenseStatus;
use App\Enums\DriverExpenseType;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverExpense;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TransferDriverExpense>
 */
class TransferDriverExpenseFactory extends Factory
{
    protected $model = TransferDriverExpense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transfer_id' => Transfer::factory(),
            'driver_id' => Driver::factory(),
            'type' => DriverExpenseType::Parking,
            'amount' => 25000,
            'currency' => 'UZS',
            // No file is written: tests that need a real receipt use the service with a fake upload.
            'receipt_path' => 'driver-expenses/test/'.Str::uuid().'.jpg',
            'receipt_mime' => 'image/jpeg',
            'receipt_size' => 1024,
            'submission_key' => (string) Str::uuid(),
            'status' => DriverExpenseStatus::New,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => DriverExpenseStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}

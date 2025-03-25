<?php

namespace App\Models;

use Carbon\Carbon;
use App\Enums\TransportType;
use App\Enums\ExpenseStatus;
use App\Observers\TransferObserver;
use App\Enums\TransportComfortLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

/**
 * @property int $id
 * @property int $transport_type
 * @property int $transport_comfort_level
 * @property int $from_city_id
 * @property int $to_city_id
 * @property float $price
 * @property float $total_price
 * @property int $company_id
 * @property string $group_number
 * @property string $send_username
 * @property int $status
 * @property int $pax
 * @property int $tour_day_expense_id
 * @property string $comment
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $date_time
 *
 * @property int $created_by
 * @property array $driver_ids
 * @property string $place_of_submission
 * @property string $route
 * @property string $passenger
 * // * @property numeric $sell_price
 * @property numeric $buy_price
 * @property string $nameplate
 * @property string $mark
 *
 * @property User $createdBy
 * @property Driver $driver
 * @property TourDayExpense $tourDayExpense
 * @property Company $company
 * @property City $fromCity
 * @property City $toCity
 */
#[ObservedBy(TransferObserver::class)]
class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transport_type',
        'transport_comfort_level',
        'from_city_id',
        'to_city_id',
        'total_price',
        'price',
        'company_id',
        'group_number',
        'send_username',
        'status',
        'pax',
        'comment',
        'tour_day_expense_id',

        'driver_ids',
        'date_time',
        'place_of_submission',

        'mark',
        'route',
        'passenger',
        //        'sell_price',
        'buy_price',
        'nameplate',
        'created_by',
    ];

    protected $casts = [
        'status' => ExpenseStatus::class,
        'transport_type' => TransportType::class,
        'transport_comfort_level' => TransportComfortLevel::class,
        'date_time' => 'datetime',
        'driver_ids' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function(Transfer $transfer) {
            $transfer->created_by = auth()->id();
        });
    }

    public function getNumber(): float|string
    {
        return 1000 + $this->id;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'from_city_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'to_city_id');
    }

    public function tourDayExpense(): BelongsTo
    {
        return $this->belongsTo(TourDayExpense::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

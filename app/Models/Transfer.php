<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 * @property int $status
 * @property int $pax
 * @property int $tour_day_expense_id
 * @property string $comment
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property string $driver
 * @property string $time
 * @property string $place_of_submission
 *
 * @property TourDayExpense $tourDayExpense
 * @property Company $company
 * @property City $fromCity
 * @property City $toCity
 */
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
        'status',
        'pax',
        'comment',
        'tour_day_expense_id',

        'driver',
        'time',
        'place_of_submission',
    ];

    protected $casts = [
        'status' => ExpenseStatus::class,
        'transport_type' => TransportType::class,
        'transport_comfort_level' => TransportComfortLevel::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
}

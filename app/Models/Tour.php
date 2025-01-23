<?php

namespace App\Models;

use App\Enums\GuideType;
use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Enums\TransportType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $company_id
 * @property string $group_number
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $pax
 * @property int $leader_pax
 * @property string $comment
 * @property int $status
 * @property int $country_id
 * @property int $created_by
 * @property int $city_id
 * @property int $price
 * @property int $expenses
 * @property int $income
 * @property int $hotel_expenses_total
 * @property TourType $type
 * @property GuideType $guide_type
 * @property string $guide_name
 * @property string $guide_phone
 * @property int $guide_price
 * @property int $transport_type
 * @property int $transport_comfort_level
 *
 * @property Company $company
 * @property User $createdBy
 * @property City $city
 * @property Country $country
 * @property Collection<TourDay> $days
 * @property Collection<TourRoomType> $roomTypes
 * @property Collection<TourDayExpense> $daysExpenses
 * @property Collection<TourHotel> $hotels
 * @property Collection<TourPassenger> $passengers
 */
class Tour extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'group_number',
        'start_date',
        'end_date',
        'arrival',
        'departure',
        'rooming',
        'pax',
        'leader_pax',
        'comment',
        'price',
        'expenses',
        'hotel_expenses_total',
        'income',
        'status',
        'country_id',
        'city_id',
        'type',
        'created_by',

        'guide_type',
        'guide_name',
        'guide_phone',
        'guide_price',

        'transport_comfort_level',
        'transport_type',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'type' => TourType::class,
        'status' => TourStatus::class,
        'guide_type' => GuideType::class,
        'transport_type' => TransportType::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TourDay::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(TourRoomType::class);
    }

    public function daysExpenses(): HasManyThrough
    {
        return $this->hasManyThrough(TourDayExpense::class, TourDay::class);
    }

    public function hotels(): HasMany
    {
        return $this->hasMany(TourHotel::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TourPassenger::class);
    }
}

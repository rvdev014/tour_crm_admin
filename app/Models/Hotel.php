<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int $country_id
 * @property int $city_id
 * @property int $booking_cancellation_days
 * @property string $inn
 * @property string $company_name
 * @property string $address
 * @property float $rate
 * @property string $phone
 * @property string $comment
 *
 * @property City $city
 * @property Country $country
 * @property Collection<HotelRoomType> $roomTypes
 * @property Collection<HotelPeriod> $periods
 * @property Collection<HotelPhone> $phones
 */
class Hotel extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'country_id',
        'city_id',
        'booking_cancellation_days',
        'inn',
        'company_name',
        'address',
        'rate',
        'phone',
        'comment',
    ];

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class)->orderBy('id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(HotelPeriod::class)->orderBy('id');
    }

    public function phones(): HasMany
    {
        return $this->hasMany(HotelPhone::class)->orderBy('id');
    }
}

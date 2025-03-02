<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int $country_id
 * @property int $city_id
 * @property int $booking_cancellation_days
 * @property string $inn
 *
 * @property City $city
 * @property Country $country
 * @property Collection<HotelRoomType> $roomTypes
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
}

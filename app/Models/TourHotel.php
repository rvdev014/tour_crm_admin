<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tour_id
 * @property int $hotel_id
 * @property int $hotel_room_type_id
 * @property int $status
 * @property int $pax
 * @property int $price
 * @property int $additional_percent
 *
 * @property Tour $tour
 * @property Hotel $hotel
 * @property Collection<HotelRoomType> $roomTypes
 */
class TourHotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'hotel_id',
        'hotel_room_type_id',
        'status',
        'pax',
        'price',
        'additional_percent'
    ];


    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class)->orderBy('id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $tour_id
 * @property int $hotel_room_type_id
 * @property int $amount
 *
 * @property Tour $tour
 * @property HotelRoomType $hotelRoomType
 */
class TourHotelRoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'hotel_room_type_id',
        'amount',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function hotelRoomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class);
    }
}

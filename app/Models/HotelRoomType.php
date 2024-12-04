<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $room_type_id
 * @property float $price
 *
 * @property RoomType $room_type
 * @property Hotel $hotel
 */
class HotelRoomType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'price'
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->orderBy('id');
    }
}

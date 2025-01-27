<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $tour_id
 * @property int $room_type_id
 * @property int $amount
 *
 * @property Tour $tour
 * @property RoomType $roomType
 */
class TourRoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'room_type_id',
        'amount',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}

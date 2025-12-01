<?php

namespace App\Models;

use App\Enums\RoomPersonType;
use App\Enums\RoomSeasonType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $tour_id
 * @property int $room_type_id
 * @property int $amount
 * @property RoomPersonType $person_type
// * @property RoomSeasonType $season_type
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
        'person_type',
//        'season_type'
    ];

    protected $casts = [
        'person_type' => RoomPersonType::class,
//        'season_type' => RoomSeasonType::class
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

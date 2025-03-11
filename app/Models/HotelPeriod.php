<?php

namespace App\Models;

use App\Enums\RoomSeasonType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $hotel_id
 * @property string $start_date
 * @property string $end_date
 * @property RoomSeasonType $season_type
 */
class HotelPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'start_date',
        'end_date',
        'season_type',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'season_type' => RoomSeasonType::class,
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}

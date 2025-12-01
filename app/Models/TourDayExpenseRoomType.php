<?php

namespace App\Models;

use App\Enums\RoomPersonType;
use App\Enums\RoomSeasonType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $tour_day_expense_id
 * @property int $room_type_id
 * @property int $amount
 * @property RoomPersonType $person_type
 *
 * @property TourDayExpense $tourDayExpense
 * @property RoomType $roomType
 */
class TourDayExpenseRoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_day_expense_id',
        'room_type_id',
        'amount',
        'person_type',
    ];

    protected $casts = [
        'person_type' => RoomPersonType::class,
    ];

    public function tourDayExpense(): BelongsTo
    {
        return $this->belongsTo(TourDayExpense::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}

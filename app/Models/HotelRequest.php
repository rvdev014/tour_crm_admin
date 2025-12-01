<?php

namespace App\Models;

use DateTimeInterface;
use App\Enums\WebTourStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property DateTimeInterface $checkin_time
 * @property DateTimeInterface $checkout_time
 * @property int $room_type_id
 * @property int $user_id
 * @property int $hotel_id
 * @property string|null $comment
 * @property WebTourStatus $status
 * @property int|null $status_updated_by
 *
 * @property RoomType $roomType
 * @property User $user
 * @property User $statusUpdatedBy
 * @property Hotel $hotel
 */
class HotelRequest extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'checkin_time',
        'checkout_time',
        'room_type_id',
        'user_id',
        'hotel_id',
        'status',
        'status_updated_by',
        'comment',
    ];
    
    protected $casts = [
        'checkin_time' => 'datetime',
        'checkout_time' => 'datetime',
        'status' => WebTourStatus::class,
    ];
    
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function statusUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }
    
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}

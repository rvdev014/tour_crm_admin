<?php

namespace App\Models;

use App\Enums\WebTourStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A flight search request submitted from the website. There is no live
 * flight search/booking integration — this simply captures the trip
 * details as a lead for an operator to follow up on, the same way
 * HotelRequest does for hotels.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $from
 * @property string $to
 * @property Carbon $departure_date
 * @property Carbon|null $return_date
 * @property int $passengers_count
 * @property string|null $cabin_class
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $comment
 * @property WebTourStatus $status
 * @property int|null $status_updated_by
 * @property-read User|null $user
 * @property-read User|null $statusUpdatedBy
 */
class FlightRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from',
        'to',
        'departure_date',
        'return_date',
        'passengers_count',
        'cabin_class',
        'phone',
        'email',
        'comment',
        'status',
        'status_updated_by',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'status' => WebTourStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }
}

<?php

namespace App\Models;

use DateTime;
use App\Enums\TransportClassEnum;
use App\Enums\TransferRequestStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property TransferRequestStatus $status
 * @property int|null $user_id
 * @property int $from
 * @property int $to
 * @property float $distance
 * @property float $total_fare
 * @property string $from_coords
 * @property string $to_coords
 * @property DateTime $date_time
 * @property int $passengers_count
 * @property int $parent_id
 * @property string $terminal_name
 * @property string $fio
 * @property string $phone
 * @property string|null $comment
 * @property string|null $text_on_sign
 * @property string|null $payment_type
 * @property string|null $payment_card
 * @property string|null $payment_holder_name
 * @property string|null $payment_valid_until
 * @property DateTime $created_at
 * @property DateTime $updated_at
 *
 * @property TransferRequest|null $parent
 * @property Collection<TransferRequest> $children
 * @property User|null $user
 * @property TransportClass|null $transportClass
 * @property City $fromCity
 * @property City $toCity
 */
class TransferRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'date_time' => 'datetime',
        'transport_class' => TransportClassEnum::class,
        'status' => TransferRequestStatus::class,
        'is_sample_baggage' => 'boolean',
        'activate_flight_tracking' => 'boolean',
        'baggage_count' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TransferRequest::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TransferRequest::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'from_city_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'to_city_id');
    }

    public function transportClass(): BelongsTo
    {
        return $this->belongsTo(TransportClass::class, 'transport_class_id');
    }
}

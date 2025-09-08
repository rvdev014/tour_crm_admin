<?php

namespace App\Models;

use DateTime;
use App\Enums\TransportClassEnum;
use App\Enums\TransferRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property TransferRequestStatus $status
 * @property int|null $user_id
 * @property int $from
 * @property int $to
 * @property float $distance
 * @property string $from_coords
 * @property string $to_coords
 * @property DateTime $date_time
 * @property int $passengers_count
 * @property string $fio
 * @property string $phone
 * @property string|null $comment
 * @property string|null $payment_type
 * @property string|null $payment_card
 * @property string|null $payment_holder_name
 * @property string|null $payment_valid_until
 * @property DateTime $created_at
 * @property DateTime $updated_at
 *
 * @property User|null $user
 * @property TransportClass|null $transportClass
 * @property City $fromCity
 * @property City $toCity
 */
class TransferRequest extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'status',
        'user_id',
        'from',
        'to',
        'distance',
        'from_coords',
        'to_coords',
        'date_time',
        'passengers_count',
        'transport_class_id',
        'fio',
        'phone',
        'comment',
        'is_sample_baggage',
        'baggage_count',
        'terminal_name',
        'text_on_sign',
        'activate_flight_tracking',
    ];
    
    protected $casts = [
        'date_time' => 'datetime',
        'transport_class' => TransportClassEnum::class,
        'status' => TransferRequestStatus::class,
        'is_sample_baggage' => 'boolean',
        'activate_flight_tracking' => 'boolean',
        'baggage_count' => 'integer',
    ];
    
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
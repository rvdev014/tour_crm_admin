<?php

namespace App\Models;

use App\Enums\TransportClass;
use App\Enums\TransferRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property TransferRequestStatus $status
 * @property int|null $user_id
 * @property int $from_city_id
 * @property int $to_city_id
 * @property \DateTime $date_time
 * @property int $passengers_count
 * @property TransportClass|null $transport_class
 * @property string $fio
 * @property string $phone
 * @property string|null $comment
 * @property string|null $payment_type
 * @property string|null $payment_card
 * @property string|null $payment_holder_name
 * @property string|null $payment_valid_until
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * 
 * @property User|null $user
 * @property City $fromCity
 * @property City $toCity
 */
class TransferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'user_id',
        'from_city_id',
        'to_city_id',
        'date_time',
        'passengers_count',
        'transport_class',
        'fio',
        'phone',
        'comment',
        'payment_type',
        'payment_card',
        'payment_holder_name',
        'payment_valid_until',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'transport_class' => TransportClass::class,
        'status' => TransferRequestStatus::class,
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
}
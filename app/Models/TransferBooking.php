<?php

namespace App\Models;

use App\Enums\TransferBookingStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The order aggregate for a customer-facing transfer booking. Owns one
 * TransferRequest leg (one-way) or two (return trip: departure + return),
 * plus the extras attached to the whole booking.
 *
 * @property int $id
 * @property string $reference
 * @property int|null $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $hotel_address
 * @property string|null $flight_data
 * @property string|null $notes
 * @property bool $is_round_trip
 * @property float $transfers_total
 * @property float $extras_total
 * @property float $total
 * @property string $currency
 * @property TransferBookingStatus $status
 * @property int|null $status_updated_by
 * @property User|null $user
 * @property User|null $statusUpdatedBy
 * @property Collection<TransferRequest> $legs
 */
class TransferBooking extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_round_trip' => 'boolean',
        'transfers_total' => 'decimal:2',
        'extras_total' => 'decimal:2',
        'total' => 'decimal:2',
        'status' => TransferBookingStatus::class,
        'submitted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TransferBooking $booking) {
            if (empty($booking->reference)) {
                $booking->reference = static::generateUniqueReference();
            }
        });
    }

    public static function generateUniqueReference(): string
    {
        do {
            $reference = 'TR-'.now()->format('Y').'-'.strtoupper(Str::random(6));
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(TransferRequest::class)->orderBy('direction');
    }

    public function extras(): BelongsToMany
    {
        return $this->belongsToMany(TransferExtra::class, 'transfer_booking_extras')
            ->withPivot(['quantity', 'unit_price', 'total_price']);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}

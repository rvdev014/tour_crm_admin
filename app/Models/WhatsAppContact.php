<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $wa_id
 * @property string|null $phone
 * @property string|null $profile_name
 * @property int|null $user_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $last_inbound_at
 * @property int $unread_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User|null $user
 * @property WhatsAppMessage[]|\Illuminate\Database\Eloquent\Collection $messages
 */
class WhatsAppContact extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'wa_id',
        'phone',
        'profile_name',
        'user_id',
        'last_message_at',
        'last_inbound_at',
        'unread_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_inbound_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('last_message_at');
    }

    /**
     * Free-form text replies are only allowed within 24 hours of the
     * client's last inbound message; outside that window Meta rejects
     * anything but a pre-approved template message.
     */
    public function isWithin24HourWindow(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->gt(now()->subHours(24));
    }

    public function displayName(): string
    {
        return $this->profile_name ?: $this->phone ?: $this->wa_id;
    }
}

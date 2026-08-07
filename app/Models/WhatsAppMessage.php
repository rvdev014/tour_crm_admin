<?php

namespace App\Models;

use App\Enums\WhatsAppDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $whatsapp_contact_id
 * @property string $wa_message_id
 * @property WhatsAppDirection $direction
 * @property WhatsAppMessageType $type
 * @property string|null $body
 * @property array|null $payload
 * @property WhatsAppMessageStatus $status
 * @property string|null $error_message
 * @property int|null $sent_by_user_id
 * @property Carbon|null $wa_timestamp
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property WhatsAppContact $contact
 * @property User|null $sentBy
 * @property Attachment[]|\Illuminate\Database\Eloquent\Collection $attachments
 */
class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'whatsapp_contact_id',
        'wa_message_id',
        'direction',
        'type',
        'body',
        'payload',
        'status',
        'error_message',
        'sent_by_user_id',
        'wa_timestamp',
    ];

    protected $casts = [
        'direction' => WhatsAppDirection::class,
        'type' => WhatsAppMessageType::class,
        'payload' => 'array',
        'status' => WhatsAppMessageStatus::class,
        'wa_timestamp' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}

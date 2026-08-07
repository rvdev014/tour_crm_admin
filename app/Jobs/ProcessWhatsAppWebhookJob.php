<?php

namespace App\Jobs;

use App\Enums\AttachmentType;
use App\Enums\WhatsAppDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Handles a single Meta WhatsApp webhook payload. Kept out of the request
 * cycle so the webhook controller can always answer Meta within its
 * timeout — Meta disables webhooks that respond slowly or non-2xx.
 */
class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected array $payload) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        foreach (data_get($this->payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value = data_get($change, 'value', []);

                $this->handleMessages($value, $whatsapp);
                $this->handleStatuses($value);
            }
        }
    }

    protected function handleMessages(array $value, WhatsAppService $whatsapp): void
    {
        $messages = data_get($value, 'messages', []);

        if (empty($messages)) {
            return;
        }

        $profileName = data_get($value, 'contacts.0.profile.name');

        foreach ($messages as $incoming) {
            $waId = data_get($incoming, 'from');
            $waMessageId = data_get($incoming, 'id');
            $type = WhatsAppMessageType::tryFrom(data_get($incoming, 'type')) ?? WhatsAppMessageType::Unsupported;
            $timestamp = data_get($incoming, 'timestamp');

            if (! $waId || ! $waMessageId) {
                continue;
            }

            $contact = WhatsAppContact::firstOrNew(['wa_id' => $waId]);
            $contact->phone = $contact->phone ?: $waId;
            $contact->profile_name = $profileName ?: $contact->profile_name;
            $contact->last_inbound_at = now();
            $contact->last_message_at = now();
            $contact->unread_count = $contact->unread_count + 1;

            if (! $contact->exists) {
                $contact->user_id = $this->matchUser($waId)?->id;
            }

            $contact->save();

            // updateOrCreate on the unique wa_message_id makes this safe
            // against Meta's at-least-once webhook retries.
            $message = WhatsAppMessage::updateOrCreate(
                ['wa_message_id' => $waMessageId],
                [
                    'whatsapp_contact_id' => $contact->id,
                    'direction' => WhatsAppDirection::In,
                    'type' => $type,
                    'body' => $this->extractBody($incoming, $type),
                    'payload' => $incoming,
                    'status' => WhatsAppMessageStatus::Delivered,
                    'wa_timestamp' => $timestamp ? now()->createFromTimestamp((int) $timestamp) : now(),
                ]
            );

            if ($mediaId = $this->extractMediaId($incoming, $type)) {
                DownloadWhatsAppMediaJob::dispatch($message->id, $mediaId, $this->attachmentCategory($type));
            }
        }
    }

    protected function handleStatuses(array $value): void
    {
        foreach (data_get($value, 'statuses', []) as $statusUpdate) {
            $waMessageId = data_get($statusUpdate, 'id');
            $status = WhatsAppMessageStatus::tryFrom(data_get($statusUpdate, 'status'));

            if (! $waMessageId || ! $status) {
                continue;
            }

            $message = WhatsAppMessage::where('wa_message_id', $waMessageId)->first();

            if (! $message) {
                continue;
            }

            $message->status = $status;

            if ($status === WhatsAppMessageStatus::Failed) {
                $message->error_message = data_get($statusUpdate, 'errors.0.title', $message->error_message);
            }

            $message->save();
        }
    }

    protected function extractBody(array $incoming, WhatsAppMessageType $type): ?string
    {
        return match ($type) {
            WhatsAppMessageType::Text => data_get($incoming, 'text.body'),
            WhatsAppMessageType::Image, WhatsAppMessageType::Video, WhatsAppMessageType::Document => data_get($incoming, "{$type->value}.caption"),
            WhatsAppMessageType::Location => trim(
                data_get($incoming, 'location.name', '').' '.data_get($incoming, 'location.address', '')
            ) ?: null,
            default => null,
        };
    }

    protected function extractMediaId(array $incoming, WhatsAppMessageType $type): ?string
    {
        if (! $type->hasMedia()) {
            return null;
        }

        return data_get($incoming, "{$type->value}.id");
    }

    protected function attachmentCategory(WhatsAppMessageType $type): AttachmentType
    {
        return match ($type) {
            WhatsAppMessageType::Image, WhatsAppMessageType::Sticker => AttachmentType::Photo,
            WhatsAppMessageType::Video => AttachmentType::Video,
            WhatsAppMessageType::Audio => AttachmentType::Audio,
            default => AttachmentType::Document,
        };
    }

    /**
     * Best-effort match of an inbound WhatsApp number to an existing CRM
     * user, comparing the last 9 digits so formatting differences
     * (+998..., 998..., with/without spaces) don't block the match.
     */
    protected function matchUser(string $waId): ?User
    {
        $suffix = substr(preg_replace('/\D/', '', $waId), -9);

        if (strlen($suffix) < 9) {
            return null;
        }

        return User::whereRaw("regexp_replace(phone, '[^0-9]', '', 'g') LIKE ?", ['%'.$suffix])->first();
    }
}

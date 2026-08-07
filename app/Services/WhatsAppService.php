<?php

namespace App\Services;

use App\Enums\AttachmentType;
use App\Enums\WhatsAppDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Attachment;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Thin wrapper around the Meta WhatsApp Business Cloud API.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class WhatsAppService
{
    /**
     * Thrown (as \DomainException) when trying to send a free-form message
     * to a contact outside the 24-hour customer service window.
     */
    public const OUTSIDE_WINDOW_MESSAGE = 'Contact is outside the 24-hour reply window; send a template message instead.';

    protected function client(): PendingRequest
    {
        return Http::timeout(15)
            ->withToken(config('whatsapp.access_token'))
            ->baseUrl(rtrim(config('whatsapp.base_url'), '/').'/'.config('whatsapp.api_version'));
    }

    /**
     * Send a free-form text reply. Only allowed within the 24h window
     * opened by the client's last inbound message.
     */
    public function sendText(WhatsAppContact $contact, string $body, User $sender): WhatsAppMessage
    {
        if (! $contact->isWithin24HourWindow()) {
            throw new \DomainException(self::OUTSIDE_WINDOW_MESSAGE);
        }

        $message = $contact->messages()->create([
            'wa_message_id' => 'pending-'.Str::uuid(),
            'direction' => WhatsAppDirection::Out,
            'type' => WhatsAppMessageType::Text,
            'body' => $body,
            'status' => WhatsAppMessageStatus::Pending,
            'sent_by_user_id' => $sender->id,
            'wa_timestamp' => now(),
        ]);

        $response = $this->client()->post('/'.config('whatsapp.phone_number_id').'/messages', [
            'messaging_product' => 'whatsapp',
            'to' => $contact->wa_id,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);

        $this->applySendResult($message, $response);

        $contact->update(['last_message_at' => now()]);

        return $message;
    }

    /**
     * Send a pre-approved template message — the only way to (re)open a
     * conversation once the 24h free-form window has closed.
     */
    public function sendTemplate(
        WhatsAppContact $contact,
        string $templateName,
        string $languageCode,
        array $components,
        User $sender
    ): WhatsAppMessage {
        $message = $contact->messages()->create([
            'wa_message_id' => 'pending-'.Str::uuid(),
            'direction' => WhatsAppDirection::Out,
            'type' => WhatsAppMessageType::Template,
            'body' => $templateName,
            'status' => WhatsAppMessageStatus::Pending,
            'sent_by_user_id' => $sender->id,
            'wa_timestamp' => now(),
        ]);

        $response = $this->client()->post('/'.config('whatsapp.phone_number_id').'/messages', [
            'messaging_product' => 'whatsapp',
            'to' => $contact->wa_id,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ]);

        $this->applySendResult($message, $response);

        $contact->update(['last_message_at' => now()]);

        return $message;
    }

    protected function applySendResult(WhatsAppMessage $message, \Illuminate\Http\Client\Response $response): void
    {
        if ($response->successful()) {
            $waMessageId = data_get($response->json(), 'messages.0.id');

            $message->update([
                'wa_message_id' => $waMessageId ?: $message->wa_message_id,
                'status' => WhatsAppMessageStatus::Sent,
                'payload' => $response->json(),
            ]);

            return;
        }

        $error = data_get($response->json(), 'error.message', $response->body());

        Log::error('WhatsApp send failed: '.$error, ['response' => $response->json()]);

        $message->update([
            'status' => WhatsAppMessageStatus::Failed,
            'error_message' => $error,
            'payload' => $response->json(),
        ]);
    }

    /**
     * Send a read receipt so the client sees blue ticks in their app.
     */
    public function markAsRead(string $waMessageId): void
    {
        try {
            $this->client()->post('/'.config('whatsapp.phone_number_id').'/messages', [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $waMessageId,
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp markAsRead failed: '.$e->getMessage());
        }
    }

    /**
     * Resolve a media id to a short-lived URL, download it, and store it
     * as an Attachment on the given message.
     */
    public function downloadMedia(WhatsAppMessage $message, string $mediaId, AttachmentType $category): ?Attachment
    {
        $meta = $this->client()->get('/'.$mediaId);

        if (! $meta->successful()) {
            Log::error('WhatsApp media lookup failed: '.$meta->body());

            return null;
        }

        $url = data_get($meta->json(), 'url');
        $mimeType = data_get($meta->json(), 'mime_type', 'application/octet-stream');

        if (! $url) {
            return null;
        }

        $fileResponse = Http::timeout(30)->withToken(config('whatsapp.access_token'))->get($url);

        if (! $fileResponse->successful()) {
            Log::error('WhatsApp media download failed: '.$fileResponse->status());

            return null;
        }

        $extension = Str::afterLast($mimeType, '/');
        $path = "whatsapp/{$mediaId}.{$extension}";
        Storage::disk('public')->put($path, $fileResponse->body());

        return $message->attachments()->create([
            'file_name' => basename($path),
            'file_path' => $path,
            'file_type' => $mimeType,
            'file_size' => strlen($fileResponse->body()),
            'category' => $category,
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Enums\AttachmentType;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Downloads a single WhatsApp media attachment (image/document/audio/video)
 * and stores it as an Attachment on the owning message. Split out from
 * ProcessWhatsAppWebhookJob so a slow/failed media fetch never blocks the
 * rest of the webhook payload from being processed.
 */
class DownloadWhatsAppMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected int $whatsAppMessageId,
        protected string $mediaId,
        protected AttachmentType $category
    ) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $message = WhatsAppMessage::find($this->whatsAppMessageId);

        if (! $message) {
            return;
        }

        $whatsapp->downloadMedia($message, $this->mediaId, $this->category);
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives webhook events from the Meta WhatsApp Business Cloud API.
 *
 * @see https://developers.facebook.com/docs/graph-api/webhooks/getting-started
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * One-time subscription handshake Meta performs when the webhook URL
     * is registered (and whenever it re-verifies it).
     */
    public function verify(Request $request): Response
    {
        if (
            $request->query('hub_mode') === 'subscribe'
            && $request->query('hub_verify_token') === config('whatsapp.verify_token')
        ) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Verification failed.', 403);
    }

    /**
     * Receives message and status-update events. Must answer fast — Meta
     * retries (and eventually disables) webhooks that respond slowly or
     * with a non-2xx status — so all real work happens in a queued job.
     */
    public function receive(Request $request): Response
    {
        ProcessWhatsAppWebhookJob::dispatch($request->all());

        return response()->noContent();
    }
}

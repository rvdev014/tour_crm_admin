<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the X-Hub-Signature-256 header Meta signs every webhook POST
 * with, so this open (unauthenticated) endpoint can't be forged.
 *
 * @see https://developers.facebook.com/docs/graph-api/webhooks/getting-started#validating-payloads
 */
class VerifyWhatsAppSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // Meta's GET verification handshake carries no signature header.
        if ($request->isMethod('get')) {
            return $next($request);
        }

        $signatureHeader = $request->header('X-Hub-Signature-256', '');
        $appSecret = config('whatsapp.app_secret');

        if (! $appSecret || ! str_starts_with($signatureHeader, 'sha256=')) {
            Log::warning('WhatsApp webhook: missing signature or app secret not configured.');

            return response('Invalid signature.', 403);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $appSecret);
        $provided = substr($signatureHeader, strlen('sha256='));

        if (! hash_equals($expected, $provided)) {
            Log::warning('WhatsApp webhook: signature mismatch.');

            return response('Invalid signature.', 403);
        }

        return $next($request);
    }
}

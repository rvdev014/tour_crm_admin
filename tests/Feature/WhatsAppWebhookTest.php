<?php

namespace Tests\Feature;

use App\Enums\WhatsAppDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected const SECRET = 'test-app-secret';

    protected const VERIFY_TOKEN = 'test-verify-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.app_secret' => self::SECRET,
            'whatsapp.verify_token' => self::VERIFY_TOKEN,
        ]);
    }

    public function test_verification_handshake_echoes_challenge_with_correct_token()
    {
        $response = $this->get('/api/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => self::VERIFY_TOKEN,
            'hub_challenge' => 'challenge-123',
        ]));

        $response->assertOk();
        $response->assertSeeText('challenge-123');
    }

    public function test_verification_handshake_rejects_wrong_token()
    {
        $response = $this->get('/api/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong-token',
            'hub_challenge' => 'challenge-123',
        ]));

        $response->assertStatus(403);
    }

    public function test_webhook_rejects_request_with_bad_signature()
    {
        $response = $this->call(
            'POST',
            '/api/webhooks/whatsapp',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Hub-Signature-256' => 'sha256=not-the-real-signature',
                'Content-Type' => 'application/json',
            ]),
            json_encode($this->inboundTextPayload())
        );

        $response->assertStatus(403);
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_webhook_stores_inbound_text_message_and_creates_contact()
    {
        $response = $this->signedPost($this->inboundTextPayload());

        $response->assertNoContent();

        $this->assertDatabaseHas('whatsapp_contacts', [
            'wa_id' => '998901234567',
            'profile_name' => 'John Client',
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_message_id' => 'wamid.TEST123',
            'body' => 'Hello, I need a tour',
            'direction' => WhatsAppDirection::In->value,
        ]);

        $contact = WhatsAppContact::where('wa_id', '998901234567')->firstOrFail();
        $this->assertEquals(1, $contact->unread_count);
        $this->assertNotNull($contact->last_inbound_at);
    }

    public function test_webhook_is_idempotent_when_meta_retries_the_same_payload()
    {
        $payload = $this->inboundTextPayload();

        $this->signedPost($payload)->assertNoContent();
        $this->signedPost($payload)->assertNoContent();

        $this->assertDatabaseCount('whatsapp_messages', 1);

        $contact = WhatsAppContact::where('wa_id', '998901234567')->firstOrFail();
        $this->assertEquals(2, $contact->unread_count, 'unread_count increments per delivery, only the message row is deduped');
    }

    public function test_webhook_updates_outbound_message_status_from_delivery_receipt()
    {
        $contact = WhatsAppContact::create([
            'wa_id' => '998901234567',
            'phone' => '998901234567',
            'last_inbound_at' => now(),
        ]);

        $message = WhatsAppMessage::create([
            'whatsapp_contact_id' => $contact->id,
            'wa_message_id' => 'wamid.OUTBOUND456',
            'direction' => WhatsAppDirection::Out,
            'body' => 'Your tour is confirmed.',
            'status' => WhatsAppMessageStatus::Sent,
            'wa_timestamp' => now(),
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'statuses' => [[
                            'id' => 'wamid.OUTBOUND456',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                            'recipient_id' => '998901234567',
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->signedPost($payload)->assertNoContent();

        $this->assertEquals(
            WhatsAppMessageStatus::Delivered,
            $message->fresh()->status
        );
    }

    protected function inboundTextPayload(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '15550001234',
                            'phone_number_id' => 'PHONE_NUMBER_ID',
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'John Client'],
                            'wa_id' => '998901234567',
                        ]],
                        'messages' => [[
                            'from' => '998901234567',
                            'id' => 'wamid.TEST123',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'Hello, I need a tour'],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * Posts a webhook payload with a correctly computed X-Hub-Signature-256
     * header, mirroring exactly what Meta sends and what
     * VerifyWhatsAppSignature validates.
     */
    protected function signedPost(array $payload): TestResponse
    {
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        return $this->call(
            'POST',
            '/api/webhooks/whatsapp',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Hub-Signature-256' => $signature,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
            $body
        );
    }
}

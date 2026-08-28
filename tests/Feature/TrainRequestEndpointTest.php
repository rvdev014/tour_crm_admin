<?php

namespace Tests\Feature;

use App\Enums\WebTourStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainRequestEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'from' => 'Tashkent',
            'to' => 'Samarkand',
            'departure_date' => now()->addDays(3)->toDateString(),
            'return_date' => now()->addDays(5)->toDateString(),
            'passengers_count' => 2,
            'wagon_class' => 'kupe',
            'comment' => 'Window seats please',
        ], $overrides);
    }

    public function test_unauthenticated_user_cannot_submit_train_request()
    {
        $response = $this->postJson('/api/train-requests', $this->validPayload());

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_submit_train_request()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/train-requests', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'from',
                    'to',
                    'departure_date',
                    'return_date',
                    'passengers_count',
                    'wagon_class',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('train_requests', [
            'user_id' => $user->id,
            'from' => 'Tashkent',
            'to' => 'Samarkand',
            'passengers_count' => 2,
            'wagon_class' => 'kupe',
            'status' => WebTourStatus::New->value,
        ]);
    }

    public function test_phone_and_email_fall_back_to_authenticated_user()
    {
        $user = User::factory()->create([
            'phone' => '+998901234567',
            'email' => 'traveller@example.com',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/train-requests', $this->validPayload())
            ->assertStatus(201);

        $this->assertDatabaseHas('train_requests', [
            'user_id' => $user->id,
            'phone' => '+998901234567',
            'email' => 'traveller@example.com',
        ]);
    }

    public function test_explicit_phone_and_email_override_user_defaults()
    {
        $user = User::factory()->create([
            'phone' => '+998901234567',
            'email' => 'traveller@example.com',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/train-requests', $this->validPayload([
            'phone' => '+998907654321',
            'email' => 'other@example.com',
        ]))->assertStatus(201);

        $this->assertDatabaseHas('train_requests', [
            'user_id' => $user->id,
            'phone' => '+998907654321',
            'email' => 'other@example.com',
        ]);
    }

    public function test_missing_required_fields_are_rejected()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/train-requests', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from', 'to', 'departure_date', 'passengers_count']);
    }

    public function test_departure_date_in_the_past_is_rejected()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/train-requests', $this->validPayload([
            'departure_date' => now()->subDay()->toDateString(),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['departure_date']);
    }

    public function test_return_date_before_departure_date_is_rejected()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/train-requests', $this->validPayload([
            'departure_date' => now()->addDays(5)->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['return_date']);
    }

    public function test_invalid_wagon_class_is_rejected()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/train-requests', $this->validPayload([
            'wagon_class' => 'business',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['wagon_class']);
    }

    /**
     * @dataProvider validWagonClassProvider
     */
    public function test_each_valid_wagon_class_is_accepted(string $wagonClass)
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/train-requests', $this->validPayload([
            'wagon_class' => $wagonClass,
        ]));

        $response->assertStatus(201);
    }

    public static function validWagonClassProvider(): array
    {
        return [
            'seated' => ['seated'],
            'platskart' => ['platskart'],
            'kupe' => ['kupe'],
            'sv' => ['sv'],
        ];
    }

    public function test_passengers_count_above_fifty_is_rejected()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/train-requests', $this->validPayload([
            'passengers_count' => 51,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['passengers_count']);
    }

    public function test_passengers_count_of_fifty_is_accepted()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/train-requests', $this->validPayload([
            'passengers_count' => 50,
        ]));

        $response->assertStatus(201);
    }
}

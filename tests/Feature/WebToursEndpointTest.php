<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tour;
use App\Models\WebTourRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebToursEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_web_tours()
    {
        $response = $this->getJson('/api/me/web-tours');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_their_web_tours()
    {
        $user = User::factory()->create();
        
        // Create tours for the test
        $tour1 = Tour::factory()->create();
        $tour2 = Tour::factory()->create();
        
        // Create web tour requests for the user
        $webTourRequest1 = WebTourRequest::factory()->create([
            'user_id' => $user->id,
            'tour_id' => $tour1->id,
        ]);
        
        $webTourRequest2 = WebTourRequest::factory()->create([
            'user_id' => $user->id,
            'tour_id' => $tour2->id,
        ]);

        // Create a web tour request for another user (should not be returned)
        $otherUser = User::factory()->create();
        $otherTour = Tour::factory()->create();
        WebTourRequest::factory()->create([
            'user_id' => $otherUser->id,
            'tour_id' => $otherTour->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/web-tours');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'tour_id',
                        'start_date',
                        'phone',
                        'citizenship',
                        'comment',
                        'travellers_count',
                        'tour_type',
                        'status',
                        'created_at',
                        'updated_at',
                        'tour',
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data');

        // Verify that the returned web tours belong to the authenticated user
        $responseData = $response->json('data');
        $returnedIds = collect($responseData)->pluck('id')->sort()->values();
        $expectedIds = collect([$webTourRequest1->id, $webTourRequest2->id])->sort()->values();
        
        $this->assertEquals($expectedIds, $returnedIds);
    }

    public function test_web_tours_are_ordered_by_latest()
    {
        $user = User::factory()->create();
        
        // Create tours
        $tour1 = Tour::factory()->create();
        $tour2 = Tour::factory()->create();
        $tour3 = Tour::factory()->create();
        
        // Create web tour requests at different times
        $oldRequest = WebTourRequest::factory()->create([
            'user_id' => $user->id,
            'tour_id' => $tour1->id,
            'created_at' => now()->subDays(2),
        ]);
        
        $middleRequest = WebTourRequest::factory()->create([
            'user_id' => $user->id,
            'tour_id' => $tour2->id,
            'created_at' => now()->subDay(),
        ]);
        
        $latestRequest = WebTourRequest::factory()->create([
            'user_id' => $user->id,
            'tour_id' => $tour3->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/web-tours');

        $response->assertStatus(200);
        
        $responseData = $response->json('data');
        
        // Verify order is latest first
        $this->assertEquals($latestRequest->id, $responseData[0]['id']);
        $this->assertEquals($middleRequest->id, $responseData[1]['id']);
        $this->assertEquals($oldRequest->id, $responseData[2]['id']);
    }

    public function test_empty_web_tours_returns_empty_array()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/web-tours');

        $response->assertStatus(200)
            ->assertJson(['data' => []])
            ->assertJsonCount(0, 'data');
    }

    public function test_web_tour_includes_tour_relationship()
    {
        $user = User::factory()->create();
        $tour = Tour::factory()->create();
        
        $webTourRequest = WebTourRequest::factory()->create([
            'user_id' => $user->id,
            'tour_id' => $tour->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/web-tours');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'tour' => [
                            'id',
                            'group_number',
                            'start_date',
                            'end_date',
                            // Add more tour fields as needed
                        ]
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertEquals($tour->id, $responseData[0]['tour']['id']);
    }
}
<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $user = User::factory()->create(['name' => $name = 'John Doe']);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(302);

        $this->assertDatabaseHas('users', ['name' => $name]);
    }
}

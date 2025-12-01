<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Tour;
use App\Models\User;
use App\Enums\TourType;
use App\Services\TourService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TourGroupNumberTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_generate_101_for_the_first_tour_of_the_year()
    {
        $user = User::factory()->create(['name' => 'Admin']);
        $this->actingAs($user);
        
        $result = TourService::getGroupNumber(TourType::TPS, '2025-05-10');
        $this->assertEquals('A101-25T', $result);
    }
    
    public function test_increment_number_based_on_existing_tours_in_same_year()
    {
        $user = User::factory()->create(['name' => 'Manager']);
        $this->actingAs($user);
        
        Tour::factory()->count(3)->create(['start_date' => '2025-01-01']);
        
        $result = TourService::getGroupNumber(TourType::TPS, '2025-06-01');
        $this->assertEquals('M104-25T', $result);
    }
    
    public function test_reset_counter_to_101_for_a_new_year()
    {
        $user = User::factory()->create(['name' => 'John']);
        $this->actingAs($user);
        
        Tour::factory()->count(50)->create(['start_date' => '2024-12-31']);
        
        $result = TourService::getGroupNumber(TourType::TPS, '2025-01-01');
        $this->assertEquals('J101-25T', $result);
    }
    
    public function test_handle_different_tour_types_suffix()
    {
        $user = User::factory()->create(['name' => 'Zorro']);
        $this->actingAs($user);
        
        $result = TourService::getGroupNumber(TourType::Corporate, '2025-05-05');
        $this->assertStringEndsWith('C', $result);
        $this->assertEquals('Z101-25C', $result);
    }
    
    public function test_use_current_year_if_no_date_provided()
    {
        Carbon::setTestNow('2026-02-01');
        
        $user = User::factory()->create(['name' => 'Davron']);
        $this->actingAs($user);
        
        $result = TourService::getGroupNumber(TourType::TPS);
        $this->assertEquals('D101-26T', $result);
    }
}
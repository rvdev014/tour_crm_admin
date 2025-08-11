<?php

namespace Database\Factories;

use App\Enums\TourType;
use App\Enums\WebTourStatus;
use App\Models\Tour;
use App\Models\User;
use App\Models\WebTourRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebTourRequest>
 */
class WebTourRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tour_id' => Tour::factory(),
            'start_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'phone' => $this->faker->phoneNumber(),
            'citizenship' => $this->faker->country(),
            'comment' => $this->faker->optional()->paragraph(),
            'travellers_count' => $this->faker->numberBetween(1, 10),
            'tour_type' => $this->faker->randomElement(TourType::cases()),
            'status' => $this->faker->randomElement(WebTourStatus::cases()),
        ];
    }
}
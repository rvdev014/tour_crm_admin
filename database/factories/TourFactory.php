<?php

namespace Database\Factories;

use App\Enums\TourType;
use App\Enums\TourStatus;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tour>
 */
class TourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_number' => $this->faker->unique()->bothify('GRP-####'),
            'start_date' => $this->faker->dateTimeBetween('now', '+3 months'),
            'end_date' => $this->faker->dateTimeBetween('+3 months', '+6 months'),
            'pax' => $this->faker->numberBetween(1, 50),
            'leader_pax' => $this->faker->numberBetween(0, 5),
            'comment' => $this->faker->optional()->paragraph(),
            'status' => $this->faker->randomElement(TourStatus::cases()),
            'type' => $this->faker->randomElement(TourType::cases()),
            'requested_by' => $this->faker->name(),
            'package_name' => $this->faker->words(3, true),
            // Skip foreign key fields for simplicity - will be handled in tests
        ];
    }
}
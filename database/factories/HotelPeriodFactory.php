<?php

namespace Database\Factories;

use App\Enums\RoomSeasonType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HotelPeriod>
 */
class HotelPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+2 years');
        $endDate = $this->faker->dateTimeBetween($startDate, $startDate->format('Y-m-d') . ' +3 months');

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'season_type' => $this->faker->randomElement(RoomSeasonType::cases()),
        ];
    }

    /**
     * Summer high season period
     */
    public function summer(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $this->faker->dateTimeBetween('2024-06-01', '2024-06-30'),
            'end_date' => $this->faker->dateTimeBetween('2024-08-15', '2024-08-31'),
            'season_type' => RoomSeasonType::HighSeason,
        ]);
    }

    /**
     * Winter low season period
     */
    public function winter(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $this->faker->dateTimeBetween('2024-12-01', '2024-12-15'),
            'end_date' => $this->faker->dateTimeBetween('2025-02-15', '2025-02-28'),
            'season_type' => RoomSeasonType::LowSeason,
        ]);
    }
}

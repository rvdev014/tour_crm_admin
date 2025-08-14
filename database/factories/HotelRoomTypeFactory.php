<?php

namespace Database\Factories;

use App\Enums\RoomPersonType;
use App\Enums\RoomSeasonType;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HotelRoomType>
 */
class HotelRoomTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basePrice = $this->faker->randomFloat(2, 30, 200);
        $foreignMultiplier = $this->faker->randomFloat(2, 1.2, 2.5);

        return [
            'room_type_id' => RoomType::factory(),
            'season_type' => $this->faker->randomElement(RoomSeasonType::cases()),
            'person_type' => $this->faker->randomElement(RoomPersonType::cases()),
            'price' => $basePrice,
            'price_foreign' => $basePrice * $foreignMultiplier,
        ];
    }

    /**
     * High season pricing
     */
    public function highSeason(): static
    {
        return $this->state(fn (array $attributes) => [
            'season_type' => RoomSeasonType::HighSeason,
            'price' => $this->faker->randomFloat(2, 80, 300),
            'price_foreign' => $this->faker->randomFloat(2, 150, 500),
        ]);
    }

    /**
     * Low season pricing
     */
    public function lowSeason(): static
    {
        return $this->state(fn (array $attributes) => [
            'season_type' => RoomSeasonType::LowSeason,
            'price' => $this->faker->randomFloat(2, 25, 100),
            'price_foreign' => $this->faker->randomFloat(2, 40, 180),
        ]);
    }
}

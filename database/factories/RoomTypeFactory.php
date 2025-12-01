<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomType>
 */
class RoomTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Single Room',
                'Double Room',
                'Twin Room',
                'Triple Room',
                'Junior Suite',
                'Executive Suite',
                'Presidential Suite',
                'Family Room',
                'Deluxe Room',
                'Standard Room',
                'Economy Room',
                'Business Room'
            ]),
        ];
    }

    /**
     * Standard room types
     */
    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'Single Room',
                'Double Room',
                'Twin Room',
                'Standard Room'
            ]),
        ]);
    }

    /**
     * Luxury room types
     */
    public function luxury(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'Junior Suite',
                'Executive Suite',
                'Presidential Suite',
                'Deluxe Room'
            ]),
        ]);
    }
}

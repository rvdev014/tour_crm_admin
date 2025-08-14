<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->email(),
            'user_id' => User::factory(),
            'comment' => $this->faker->paragraph(rand(2, 5)),
            'rate' => $this->faker->randomFloat(1, 1, 5),
        ];
    }

    /**
     * Excellent review
     */
    public function excellent(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => $this->faker->randomFloat(1, 4.5, 5.0),
            'comment' => $this->faker->randomElement([
                'Excellent hotel with amazing service and beautiful rooms!',
                'Outstanding experience, highly recommend this place!',
                'Perfect location and very friendly staff.',
                'Best hotel in the area, will definitely come back!',
            ]),
        ]);
    }

    /**
     * Poor review
     */
    public function poor(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => $this->faker->randomFloat(1, 1.0, 2.5),
            'comment' => $this->faker->randomElement([
                'Very disappointed with the service.',
                'Room was not clean and staff was unhelpful.',
                'Not worth the price, facilities are outdated.',
                'Poor maintenance and noisy environment.',
            ]),
        ]);
    }

    /**
     * Average review
     */
    public function average(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => $this->faker->randomFloat(1, 3.0, 4.0),
            'comment' => $this->faker->randomElement([
                'Decent hotel for the price. Nothing special but okay.',
                'Average service, room was clean but basic.',
                'Good location but could use some improvements.',
                'Fair value for money, met basic expectations.',
            ]),
        ]);
    }
}

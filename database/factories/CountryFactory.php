<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Country>
 */
class CountryFactory extends Factory
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
                'Uzbekistan',
                'Kazakhstan',
                'Kyrgyzstan',
                'Tajikistan',
                'Turkmenistan',
                'Turkey',
                'Russia',
                'Iran',
                'Afghanistan',
                'China'
            ]),
        ];
    }

    /**
     * Uzbekistan country
     */
    public function uzbekistan(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Uzbekistan',
        ]);
    }
}

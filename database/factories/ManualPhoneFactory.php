<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ManualPhone>
 */
class ManualPhoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone_number' => $this->faker->randomElement([
                '+998901234567',
                '+998951234567',
                '+998971234567',
                '+998331234567',
                '+998651234567',
            ]),
        ];
    }

    /**
     * Generate Uzbekistan mobile number
     */
    public function mobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_number' => '+9989' . $this->faker->randomElement(['0', '1', '3', '4', '5', '9']) . $this->faker->numerify('#######'),
        ]);
    }

    /**
     * Generate Uzbekistan landline number
     */
    public function landline(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_number' => '+99871' . $this->faker->numerify('######'),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\City>
 */
class CityFactory extends Factory
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
                'Tashkent',
                'Samarkand',
                'Bukhara',
                'Khiva',
                'Nukus',
                'Andijan',
                'Namangan',
                'Fergana',
                'Urgench',
                'Termez',
                'Kokand',
                'Margilan',
                'Jizzakh',
                'Qarshi',
                'Gulistan'
            ]),
            'country_id' => Country::factory(),
        ];
    }

    /**
     * Uzbekistan cities
     */
    public function uzbekistan(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'Tashkent',
                'Samarkand', 
                'Bukhara',
                'Khiva',
                'Nukus',
                'Andijan',
                'Namangan',
                'Fergana',
                'Urgench',
                'Termez'
            ]),
            'country_id' => Country::factory()->uzbekistan(),
        ]);
    }
}

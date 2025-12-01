<?php

namespace Database\Factories;

use App\Enums\RateEnum;
use App\Models\City;
use App\Models\Country;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->companyEmail(),
            'contract_number' => $this->faker->bothify('HTL-####-??'),
            'contract_date' => $this->faker->dateTimeBetween('-2 years', '+1 year'),
            'country_id' => Country::factory(),
            'city_id' => City::factory(),
            'booking_cancellation_days' => $this->faker->numberBetween(1, 30),
            'inn' => $this->faker->bothify('#########'),
            'company_name' => $this->faker->company(),
            'address' => $this->faker->address(),
            'rate' => $this->faker->randomElement(array_column(RateEnum::cases(), 'value')),
            'website_price' => $this->faker->randomFloat(2, 50, 500),
            'comment' => $this->faker->optional()->sentence(),
            'description' => $this->faker->optional()->paragraph(),
            'latitude' => $this->faker->latitude(39.0, 42.0), // Uzbekistan coordinates range
            'longitude' => $this->faker->longitude(56.0, 73.0), // Uzbekistan coordinates range
        ];
    }

    /**
     * Create hotel with all related models
     */
    public function withRelations(): static
    {
        return $this->afterCreating(function (Hotel $hotel) {
            // Create hotel room types
            \App\Models\HotelRoomType::factory()
                ->count(rand(2, 5))
                ->for($hotel)
                ->create();

            // Create hotel periods
            \App\Models\HotelPeriod::factory()
                ->count(rand(2, 4))
                ->for($hotel)
                ->create();

            // Create reviews
            \App\Models\Review::factory()
                ->count(rand(3, 10))
                ->for($hotel, 'reviewable')
                ->create();

            // Create phones
            \App\Models\ManualPhone::factory()
                ->count(rand(1, 3))
                ->for($hotel, 'manual')
                ->create();

            // Attach facilities
            $facilityCount = rand(5, 15);
            $facilities = \App\Models\Facility::factory()->count($facilityCount)->create();
            $attachCount = min(rand(3, 8), $facilityCount);
            $hotel->facilities()->attach($facilities->random($attachCount));
        });
    }

    /**
     * Create luxury hotel
     */
    public function luxury(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => $this->faker->randomElement([RateEnum::Four->value, RateEnum::Five->value, RateEnum::Boutique->value]),
            'website_price' => $this->faker->randomFloat(2, 200, 1000),
        ]);
    }

    /**
     * Create budget hotel
     */
    public function budget(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => $this->faker->randomElement([RateEnum::One->value, RateEnum::Two->value]),
            'website_price' => $this->faker->randomFloat(2, 20, 80),
        ]);
    }
}

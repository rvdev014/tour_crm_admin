<?php

namespace Database\Factories;

use App\Enums\DriverRole;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            // Unique 9-digit national number; phone_normalized is derived by Driver::saving().
            'phone' => '+998'.$this->faker->unique()->numerify('9########'),
            // Hashed by the model's 'hashed' cast.
            'password' => 'password',
            'is_active' => true,
            'role' => DriverRole::Driver,
        ];
    }

    /** Staff account: sees every transfer, can set any status, is never assigned to a trip. */
    public function dispatcher(): static
    {
        return $this->state(fn () => ['role' => DriverRole::Dispatcher]);
    }

    /** Driver who has no cabinet access (never given a password). */
    public function withoutCabinet(): static
    {
        return $this->state(fn () => ['password' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

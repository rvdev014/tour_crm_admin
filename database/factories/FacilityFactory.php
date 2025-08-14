<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $facilityNames = [
            ['name' => 'Wi-Fi', 'name_ru' => 'Wi-Fi', 'name_en' => 'Wi-Fi'],
            ['name' => 'Pool', 'name_ru' => 'Бассейн', 'name_en' => 'Swimming Pool'],
            ['name' => 'Spa', 'name_ru' => 'СПА', 'name_en' => 'Spa'],
            ['name' => 'Gym', 'name_ru' => 'Спортзал', 'name_en' => 'Fitness Center'],
            ['name' => 'Restaurant', 'name_ru' => 'Ресторан', 'name_en' => 'Restaurant'],
            ['name' => 'Bar', 'name_ru' => 'Бар', 'name_en' => 'Bar'],
            ['name' => 'Parking', 'name_ru' => 'Парковка', 'name_en' => 'Parking'],
            ['name' => 'Air Conditioning', 'name_ru' => 'Кондиционер', 'name_en' => 'Air Conditioning'],
            ['name' => 'Room Service', 'name_ru' => 'Обслуживание номеров', 'name_en' => 'Room Service'],
            ['name' => 'Laundry', 'name_ru' => 'Прачечная', 'name_en' => 'Laundry Service'],
            ['name' => 'Conference Room', 'name_ru' => 'Конференц-зал', 'name_en' => 'Conference Room'],
            ['name' => 'Balcony', 'name_ru' => 'Балкон', 'name_en' => 'Balcony'],
            ['name' => 'Safe', 'name_ru' => 'Сейф', 'name_en' => 'Safe'],
            ['name' => 'Mini Bar', 'name_ru' => 'Мини-бар', 'name_en' => 'Mini Bar'],
            ['name' => 'Elevator', 'name_ru' => 'Лифт', 'name_en' => 'Elevator'],
        ];

        $facility = $this->faker->randomElement($facilityNames);

        return [
            'name_ru' => $facility['name_ru'],
            'name_en' => $facility['name_en'],
            'icon' => $this->faker->randomElement([
                'wifi', 'pool', 'spa', 'gym', 'restaurant', 'bar', 
                'parking', 'ac', 'room-service', 'laundry', 
                'meeting', 'balcony', 'safe', 'minibar', 'elevator'
            ]),
        ];
    }

    /**
     * Basic facilities
     */
    public function basic(): static
    {
        $basicFacilities = [
            ['name_ru' => 'Wi-Fi', 'name_en' => 'Wi-Fi'],
            ['name_ru' => 'Кондиционер', 'name_en' => 'Air Conditioning'],
            ['name_ru' => 'Парковка', 'name_en' => 'Parking'],
        ];
        
        $facility = $this->faker->randomElement($basicFacilities);
        
        return $this->state(fn (array $attributes) => [
            'name_ru' => $facility['name_ru'],
            'name_en' => $facility['name_en'],
        ]);
    }

    /**
     * Luxury facilities
     */
    public function luxury(): static
    {
        $luxuryFacilities = [
            ['name_ru' => 'СПА', 'name_en' => 'Spa'],
            ['name_ru' => 'Бассейн', 'name_en' => 'Swimming Pool'],
            ['name_ru' => 'Конференц-зал', 'name_en' => 'Conference Room'],
            ['name_ru' => 'Обслуживание номеров', 'name_en' => 'Room Service'],
        ];
        
        $facility = $this->faker->randomElement($luxuryFacilities);
        
        return $this->state(fn (array $attributes) => [
            'name_ru' => $facility['name_ru'],
            'name_en' => $facility['name_en'],
        ]);
    }
}

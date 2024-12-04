<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roomTypes = [
            'Single',
            'Double',
            'Triple',
            'Quad',
            'Queen',
        ];

        foreach ($roomTypes as $roomType) {
            if (RoomType::where('name', $roomType)->doesntExist()) {
                RoomType::create(['name' => $roomType]);
            }
        }
    }
}

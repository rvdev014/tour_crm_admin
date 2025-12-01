<?php

namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create hotels with all relationships
        Hotel::factory()
            ->count(10)
            ->withRelations()
            ->create();

        // Create some luxury hotels
        Hotel::factory()
            ->count(3)
            ->luxury()
            ->withRelations()
            ->create();

        // Create some budget hotels
        Hotel::factory()
            ->count(5)
            ->budget()
            ->withRelations()
            ->create();
    }
}

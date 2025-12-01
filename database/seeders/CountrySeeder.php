<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Country::where('name', 'Uzbekistan')->doesntExist()) {
            $uzb = Country::create(['name' => 'Uzbekistan']);
            $uzb->cities()->createMany([
                ['name' => 'Tashkent'],
                ['name' => 'Samarkand'],
                ['name' => 'Bukhara'],
                ['name' => 'Khiva'],
                ['name' => 'Shakhrisabz'],
            ]);
        }

        $countries = [
            ['name' => 'Russia'],
            ['name' => 'USA'],
            ['name' => 'Germany'],
            ['name' => 'France'],
            ['name' => 'Italy'],
            ['name' => 'Spain'],
            ['name' => 'China'],
            ['name' => 'Brazil'],
            ['name' => 'Japan'],
            ['name' => 'India'],
        ];

        foreach ($countries as $country) {
            if (Country::where('name', $country['name'])->doesntExist()) {
                Country::create($country);
            }
        }
    }
}

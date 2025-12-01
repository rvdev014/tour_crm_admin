<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::where('email', env('ADMIN_EMAIL'))->doesntExist()) {
            User::create([
                'name' => 'Admin',
                'email' => env('ADMIN_EMAIL'),
                'password' => Hash::make(env('ADMIN_PASSWORD')),
            ]);
        }

        $this->call([
            CountrySeeder::class,
            CompanySeeder::class,
            RoomTypeSeeder::class,
        ]);
    }
}

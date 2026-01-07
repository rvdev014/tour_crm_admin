<?php

namespace Database\Seeders;

use App\Enums\DefaultSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::create([
            'key' => DefaultSettings::TOUR_SBOR->value,
            'value' => 'Tour CRM',
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            ['name' => 'Company 1', 'inn' => '1234567890'],
            ['name' => 'Company 2', 'inn' => '1234567891'],
        ];

        foreach ($companies as $company) {
            if (Company::where('inn', $company['inn'])->doesntExist()) {
                Company::create($company);
            }
        }
    }
}

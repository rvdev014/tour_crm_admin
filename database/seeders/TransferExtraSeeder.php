<?php

namespace Database\Seeders;

use App\Models\TransferExtra;
use Illuminate\Database\Seeder;

class TransferExtraSeeder extends Seeder
{
    /**
     * Seed the default transfer extras (baby seat / child seat). Staff can
     * add, reprice or deactivate extras afterwards from the admin panel —
     * this just makes sure the public transfer flow isn't empty out of
     * the box on a fresh environment.
     */
    public function run(): void
    {
        $defaults = [
            [
                'name_ru' => 'Детское автокресло (для младенцев)',
                'name_en' => 'Baby seat',
                'description_ru' => 'Для детей весом от 9 до 18 кг',
                'description_en' => 'For babies weighing from 9 to 18 kg',
                'price' => 5,
                'max_quantity' => 5,
                'order' => 1,
            ],
            [
                'name_ru' => 'Детское автокресло',
                'name_en' => 'Child seat',
                'description_ru' => 'Для детей весом от 15 до 36 кг',
                'description_en' => 'For children weighing from 15 to 36 kg',
                'price' => 5,
                'max_quantity' => 5,
                'order' => 2,
            ],
        ];

        foreach ($defaults as $extra) {
            TransferExtra::query()->firstOrCreate(['name_en' => $extra['name_en']], $extra);
        }
    }
}

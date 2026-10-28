<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // English is the primary content language for the website; Russian is optional. The admin forms
    // now require the *_en field instead of *_ru, so the NOT NULL *_ru columns must accept NULL.
    private const COLUMNS = [
        'banners' => ['header_ru', 'header_en'],
        'categories' => ['name_ru', 'name_en'],
        'destinations' => ['title_ru', 'title_en'],
        'destination_sections' => ['title_ru', 'title_en'],
        'facilities' => ['name_ru', 'name_en'],
        'packages' => ['name_ru', 'name_en'],
        'services' => ['name_ru', 'name_en'],
        'transfer_extras' => ['name_ru', 'name_en'],
        'web_tours' => ['name_ru', 'name_en'],
        'web_tour_days' => ['place_name_ru', 'place_name_en'],
        'web_tour_accommodations' => ['header_ru', 'header_en'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => [$ru]) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$ru} DROP NOT NULL");
        }
    }

    public function down(): void
    {
        // Rows created without Russian text get the English one (or an empty string) so NOT NULL can be restored.
        foreach (self::COLUMNS as $table => [$ru, $en]) {
            DB::statement("UPDATE {$table} SET {$ru} = COALESCE(NULLIF({$en}, ''), '') WHERE {$ru} IS NULL");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$ru} SET NOT NULL");
        }
    }
};

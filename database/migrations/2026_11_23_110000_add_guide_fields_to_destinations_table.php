<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TEXT_COLUMNS = [
        'cuisine_intro',
        'seasons_intro',
        'season_winter',
        'season_spring',
        'season_summer',
        'season_autumn',
    ];

    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            foreach (self::TEXT_COLUMNS as $column) {
                $table->text($column.'_ru')->nullable();
                $table->text($column.'_en')->nullable();
            }
            $table->string('map_photo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $columns = ['map_photo'];
            foreach (self::TEXT_COLUMNS as $column) {
                $columns[] = $column.'_ru';
                $columns[] = $column.'_en';
            }
            $table->dropColumn($columns);
        });
    }
};

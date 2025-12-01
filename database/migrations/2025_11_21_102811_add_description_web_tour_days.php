<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('web_tour_days', function (Blueprint $table) {
            $table->text('description_ru')->nullable();
            $table->text('description_en')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_tour_days', function (Blueprint $table) {
            $table->dropColumn('description_ru');
            $table->dropColumn('description_en');
        });
    }
};

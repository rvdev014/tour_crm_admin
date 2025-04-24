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
        Schema::table('tours', function (Blueprint $table) {
            $table->double('price_result')->nullable();
            $table->double('guide_price_result')->nullable();
            $table->double('transport_price_result')->nullable();
        });

        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->double('price_result')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('price_result');
            $table->dropColumn('guide_price_result');
            $table->dropColumn('transport_price_result');
        });
    }
};

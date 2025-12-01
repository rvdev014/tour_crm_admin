<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tour_hotels', function(Blueprint $table) {
            $table->decimal('price', 10)->change();
        });

        Schema::table('museums', function(Blueprint $table) {
            $table->decimal('price_per_person', 10)->change();
        });

        Schema::table('museum_items', function(Blueprint $table) {
            $table->decimal('price_per_person', 10)->change();
        });

        Schema::table('tour_day_expenses', function(Blueprint $table) {
            $table->decimal('price', 10)->nullable()->change();
        });

        Schema::table('transports', function(Blueprint $table) {
            $table->decimal('price', 10)->change();
        });

        Schema::table('transfers', function(Blueprint $table) {
            $table->decimal('price', 10)->change();
        });

        Schema::table('hotel_room_types', function(Blueprint $table) {
            $table->decimal('price', 10)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

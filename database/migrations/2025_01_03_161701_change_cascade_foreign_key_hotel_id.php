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
            $table->dropForeign(['hotel_id']);
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
        });

        Schema::table('hotel_room_types', function(Blueprint $table) {
            $table->dropForeign(['hotel_id']);
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
        });

        Schema::table('tour_day_expenses', function(Blueprint $table) {
            $table->dropForeign(['hotel_id']);
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
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

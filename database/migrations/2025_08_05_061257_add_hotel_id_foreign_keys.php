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
        Schema::table('recommended_hotels', function (Blueprint $table) {
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
        });
        Schema::table('web_tour_accommodation_hotels', function (Blueprint $table) {
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recommended_hotels', function (Blueprint $table) {
            $table->dropForeign(['hotel_id']);
        });
        Schema::table('web_tour_accommodation_hotels', function (Blueprint $table) {
            $table->dropForeign(['hotel_id']);
        });
    }
};

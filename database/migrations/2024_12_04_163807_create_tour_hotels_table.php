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
        Schema::create('tour_hotels', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tour_id');
            $table->foreign('tour_id')->references('id')->on('tours');
            $table->bigInteger('hotel_id');
            $table->foreign('hotel_id')->references('id')->on('hotels');
            $table->bigInteger('hotel_room_type_id');
            $table->foreign('hotel_room_type_id')->references('id')->on('hotel_room_types');

            $table->integer('status');
            $table->integer('pax')->nullable();
            $table->integer('additional_percent')->nullable();
            $table->decimal('price');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_hotels');
    }
};

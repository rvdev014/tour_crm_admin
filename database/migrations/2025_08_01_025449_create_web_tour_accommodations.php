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
        Schema::create('web_tour_accommodations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('web_tour_id')->unsigned();
            $table->foreign('web_tour_id')->references('id')->on('web_tours')->onDelete('cascade');
            $table->string('header_ru');
            $table->string('header_en')->nullable();
            $table->text('description_ru')->nullable();
            $table->text('description_en')->nullable();

            $table->integer('days')->nullable();
            $table->bigInteger('city_id')->unsigned()->nullable();
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('web_tour_accommodation_hotels', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('web_tour_accommodation_id')->unsigned();
            $table->foreign('web_tour_accommodation_id')->references('id')->on('web_tour_accommodations')->onDelete('cascade');
            $table->bigInteger('hotel_id')->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_tour_accommodation_hotels');
        Schema::dropIfExists('web_tour_accommodations');
    }
};

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
        Schema::create('restaurants', function(Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('country_id')->nullable();
            $table->foreign('country_id')->references('id')->on('countries');
            $table->bigInteger('city_id')->nullable();
            $table->foreign('city_id')->references('id')->on('cities');
            $table->timestamps();
        });

        Schema::table('tour_day_expenses', function(Blueprint $table) {
            $table->integer('restaurant_id')->nullable();
            $table->foreign('restaurant_id')->references('id')->on('restaurants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_day_expenses', function(Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');
        });

        Schema::dropIfExists('restaurants');
    }
};

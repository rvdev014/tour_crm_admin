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
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('country_id');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->bigInteger('city_id');
            $table->foreign('city_id')->references('id')->on('cities');
            $table->float('price_per_person');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shows');
    }
};

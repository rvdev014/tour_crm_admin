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
        Schema::disableForeignKeyConstraints();

        Schema::create('tour_days', function(Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->bigInteger('city_id');
            $table->foreign('city_id')->references('id')->on('cities');
            $table->bigInteger('tour_id');
            $table->foreign('tour_id')->references('id')->on('tours');
            $table->integer('status')->nullable();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_days');
    }
};

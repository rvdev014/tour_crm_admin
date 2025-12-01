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
        Schema::create('trains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('train_tariffs', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('train_id')->unsigned();
            $table->foreign('train_id')->references('id')->on('trains')->onDelete('cascade');

            $table->bigInteger('from_city_id')->nullable();
            $table->foreign('from_city_id')->references('id')->on('cities');
            $table->bigInteger('to_city_id')->nullable();
            $table->foreign('to_city_id')->references('id')->on('cities');

            $table->double('class_second')->nullable();
            $table->double('class_business')->nullable();
            $table->double('class_vip')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trains');
    }
};

<?php

use App\Enums\WebTourStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('web_tours', function (Blueprint $table) {
            $table->id();
            $table->string('name_ru');
            $table->string('name_en')->nullable();
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->timestamp('deadline')->nullable();
            $table->integer('status')->default(WebTourStatus::New);

            $table->text('description_ru')->nullable();
            $table->text('description_en')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::create('web_tour_prices', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('web_tour_id')->unsigned();
            $table->foreign('web_tour_id')->references('id')->on('web_tours')->onDelete('cascade');
            $table->timestamp('from_date');
            $table->timestamp('to_date');
            $table->timestamp('deadline')->nullable();
            $table->double('price');
            $table->timestamps();
        });

        Schema::create('web_tour_days', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('web_tour_id')->unsigned();
            $table->foreign('web_tour_id')->references('id')->on('web_tours')->onDelete('cascade');
            $table->integer('day_number')->nullable();
            $table->string('place_name_ru');
            $table->string('place_name_en')->nullable();
            $table->string('photo')->nullable();
            $table->timestamp('date')->nullable();
            $table->timestamps();
        });

        Schema::create('similar_tours', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('web_tour_id')->unsigned();
            $table->foreign('web_tour_id')->references('id')->on('web_tours')->onDelete('cascade');
            $table->bigInteger('similar_web_tour_id')->unsigned();
            $table->foreign('similar_web_tour_id')->references('id')->on('web_tours')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_tours');
    }
};

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

        Schema::create('tour_day_expenses', function(Blueprint $table) {
            $table->id();
            $table->bigInteger('tour_day_id');
            $table->foreign('tour_day_id')->references('id')->on('tour_days');

            $table->integer('type');

            $table->decimal('price');
            $table->integer('pax')->nullable();
            $table->integer('status')->nullable();
            $table->text('comment')->nullable();

            $table->bigInteger('hotel_room_type_id')->nullable();
            $table->foreign('hotel_room_type_id')->references('id')->on('hotel_room_types');

            $table->string('guide_name')->nullable();
            $table->integer('guide_type')->nullable();

            $table->integer('transport_type')->nullable();
            $table->integer('transport_comfort_level')->nullable();

            $table->string('other_name')->nullable();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_day_expenses');
    }
};

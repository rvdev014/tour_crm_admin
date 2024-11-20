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
        Schema::disableForeignKeyConstraints();

        Schema::create('tour_day_expenses', function (Blueprint $table) {
            $table->id();
            $table->integer('num_people')->nullable();
            $table->timestamp('transport_time')->nullable();
            $table->decimal('price');
            $table->text('comment')->nullable();
            $table->string('location')->nullable();
            $table->string('transport_route')->nullable();
            $table->bigInteger('tour_day_id');
            $table->foreign('tour_day_id')->references('id')->on('tour_days');
            $table->bigInteger('transport_status')->nullable();
            $table->string('car_ids')->nullable();
            $table->bigInteger('driver_employee_id')->nullable();
            $table->foreign('driver_employee_id')->references('id')->on('employees');
            $table->string('ticket_type')->nullable();
            $table->integer('type');
            $table->string('ticket_time')->nullable();
            $table->bigInteger('hotel_room_type_id')->nullable();
            $table->foreign('hotel_room_type_id')->references('id')->on('hotel_room_types');
            $table->bigInteger('guide_employee_id')->nullable();
            $table->foreign('guide_employee_id')->references('id')->on('employees');
            $table->string('ticket_route')->nullable();
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

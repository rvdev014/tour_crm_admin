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
        Schema::create('tour_day_expense_room_types', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tour_day_expense_id')->unsigned();
            $table->foreign('tour_day_expense_id')->references('id')->on('tour_day_expenses')->onDelete('cascade');
            $table->bigInteger('room_type_id')->unsigned();
            $table->foreign('room_type_id')->references('id')->on('room_types');
            $table->integer('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_day_expense_room_types');
    }
};

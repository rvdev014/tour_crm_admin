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
        Schema::create('expense_guides', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tour_day_expense_id')->unsigned();
            $table->foreign('tour_day_expense_id')->references('id')->on('tour_day_expenses')->onDelete('cascade');
            $table->string('name');
            $table->string('phone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_guides');
    }
};

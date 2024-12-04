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
        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->bigInteger('museum_id')->nullable();
            $table->foreign('museum_id')->references('id')->on('museums');

            $table->bigInteger('museum_item_id')->nullable();
            $table->foreign('museum_item_id')->references('id')->on('museum_items');

            $table->string('museum_inn')->nullable();
            $table->string('museum_guide')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->dropForeign(['museum_id']);
            $table->dropColumn('museum_id');

            $table->dropForeign(['museum_item_id']);
            $table->dropColumn('museum_item_id');

            $table->dropColumn('museum_inn');
            $table->dropColumn('museum_guide');
        });
    }
};

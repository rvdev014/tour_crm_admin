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
        Schema::table('transfers', function(Blueprint $table) {
            $table->bigInteger('tour_day_expense_id')->nullable();
            $table->foreign('tour_day_expense_id')->references('id')->on('tour_day_expenses')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfers', function(Blueprint $table) {
            $table->dropForeign(['tour_day_expense_id']);
            $table->dropColumn('tour_day_expense_id');
        });
    }
};

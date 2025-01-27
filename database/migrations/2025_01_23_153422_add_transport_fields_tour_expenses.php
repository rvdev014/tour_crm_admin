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
        Schema::table('tour_day_expenses', function(Blueprint $table) {
            $table->string('transport_driver')->nullable();
            $table->string('transport_time')->nullable();
            $table->string('transport_place')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_day_expenses', function(Blueprint $table) {
            $table->dropColumn('transport_driver');
            $table->dropColumn('transport_time');
            $table->dropColumn('transport_place');
        });
    }
};

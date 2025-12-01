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
        Schema::table('tour_room_types', function (Blueprint $table) {
            $table->integer('season_type')->nullable();
            $table->integer('person_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_room_types', function (Blueprint $table) {
            $table->dropColumn('season_type');
            $table->dropColumn('person_type');
        });
    }
};

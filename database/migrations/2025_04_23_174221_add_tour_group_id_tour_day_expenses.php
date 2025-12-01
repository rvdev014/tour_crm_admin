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
            $table->bigInteger('tour_group_id')->nullable()->after('tour_day_id');
            $table->foreign('tour_group_id')->references('id')->on('tour_groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->dropForeign(['tour_group_id']);
            $table->dropColumn('tour_group_id');
        });
    }
};

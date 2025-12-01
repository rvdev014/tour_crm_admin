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
            $table->string('museum_item_ids')->nullable()->after('museum_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_day_expenses', function(Blueprint $table) {
            $table->dropColumn('museum_item_ids');
        });
    }
};

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
        Schema::table('tours', function(Blueprint $table) {
            $table->index('type');
            $table->index('price');
            $table->index('expenses');
            $table->index('income');
        });

        Schema::table('tour_days', function(Blueprint $table) {
            $table->index('tour_id');
        });

        Schema::table('tour_day_expenses', function(Blueprint $table) {
            $table->index('tour_day_id');
            $table->index('type');
            $table->index('price');
        });

        Schema::table('tour_room_types', function(Blueprint $table) {
            $table->index('tour_id');
            $table->index('room_type_id');
            $table->index('amount');
        });

        Schema::table('tour_passengers', function(Blueprint $table) {
            $table->index('tour_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function(Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['price']);
            $table->dropIndex(['expenses']);
            $table->dropIndex(['income']);
        });

        Schema::table('tour_days', function(Blueprint $table) {
            $table->dropIndex(['tour_id']);
        });

        Schema::table('tour_day_expenses', function(Blueprint $table) {
            $table->dropIndex(['tour_day_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['price']);
        });

        Schema::table('tour_room_types', function(Blueprint $table) {
            $table->dropIndex(['tour_id']);
            $table->dropIndex(['room_type_id']);
            $table->dropIndex(['amount']);
        });

        Schema::table('tour_passengers', function(Blueprint $table) {
            $table->dropIndex(['tour_id']);
        });
    }
};

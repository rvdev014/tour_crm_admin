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
            $table->decimal('total_price')->nullable();

            $table->bigInteger('from_city_id')->nullable();
            $table->foreign('from_city_id')->references('id')->on('cities');
            $table->bigInteger('to_city_id')->nullable();
            $table->foreign('to_city_id')->references('id')->on('cities');
            $table->integer('train_class')->nullable();
            $table->string('arrival_time')->nullable();
            $table->string('departure_time')->nullable();

            $table->integer('coffee_break')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->dropColumn('total_price');
            $table->dropForeign(['from_city_id']);
            $table->dropColumn('from_city_id');
            $table->dropForeign(['to_city_id']);
            $table->dropColumn('to_city_id');
            $table->dropColumn('train_class');
            $table->dropColumn('arrival_time');
            $table->dropColumn('departure_time');
            $table->dropColumn('coffee_break');
        });
    }
};

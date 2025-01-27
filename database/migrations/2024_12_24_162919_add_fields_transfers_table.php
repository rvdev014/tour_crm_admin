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
            $table->bigInteger('from_city_id')->nullable();
            $table->foreign('from_city_id')->references('id')->on('cities');
            $table->bigInteger('to_city_id')->nullable();
            $table->foreign('to_city_id')->references('id')->on('cities');
            $table->decimal('total_price')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfers', function(Blueprint $table) {
            $table->dropColumn('from_city_id');
            $table->dropColumn('to_city_id');
            $table->dropColumn('total_price');
        });
    }
};

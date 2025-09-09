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
        Schema::table('transport_classes', function (Blueprint $table) {
            $table->integer('passenger_capacity')->nullable()->change();
            $table->integer('luggage_capacity')->nullable()->change();
            $table->integer('waiting_time_included')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transport_classes', function (Blueprint $table) {
            $table->decimal('passenger_capacity', 8, 2)->nullable()->change();
            $table->decimal('luggage_capacity', 8, 2)->nullable()->change();
            $table->decimal('waiting_time_included', 8, 2)->nullable()->change();
        });
    }
};

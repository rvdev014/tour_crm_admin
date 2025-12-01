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
            $table->integer('transport_type')->nullable();
            $table->integer('transport_comfort_level')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function(Blueprint $table) {
            $table->dropColumn('transport_type');
            $table->dropColumn('transport_comfort_level');
        });
    }
};

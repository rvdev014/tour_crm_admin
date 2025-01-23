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
        Schema::table('museums', function (Blueprint $table) {
            $table->string('contract')->nullable();
        });
        Schema::table('museum_items', function (Blueprint $table) {
            $table->string('contract')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('museums', function (Blueprint $table) {
            $table->dropColumn('contract');
        });
        Schema::table('museum_items', function (Blueprint $table) {
            $table->dropColumn('contract');
        });
    }
};

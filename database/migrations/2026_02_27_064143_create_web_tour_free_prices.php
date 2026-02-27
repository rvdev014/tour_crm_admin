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
        // 1. Add 'type' to web_tours
        Schema::table('web_tours', function (Blueprint $table) {
            $table->string('type')->default('default')->after('status');
        });
        
        // 2. Create the new free prices table
        Schema::create('web_tour_free_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_tour_id')->constrained()->cascadeOnDelete();
            $table->integer('pax_count');
            $table->decimal('price', 12, 2); // Adjust precision/scale to your needs
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_tour_free_prices');
        
        Schema::table('web_tours', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

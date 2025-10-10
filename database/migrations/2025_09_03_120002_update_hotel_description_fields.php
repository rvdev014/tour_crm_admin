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
        Schema::table('hotels', function (Blueprint $table) {
            // Add description_ru field
            $table->text('description_ru')->nullable();
            
            // Rename description to description_en
            $table->renameColumn('description', 'description_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            // Rename back to description
            $table->renameColumn('description_en', 'description');
            
            // Drop description_ru field
            $table->dropColumn('description_ru');
        });
    }
};
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
        Schema::table('tours', function (Blueprint $table) {
            $table->integer('guide_type')->nullable();
            $table->string('guide_name')->nullable();
            $table->string('guide_phone')->nullable();
            $table->decimal('guide_price', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('guide_type');
            $table->dropColumn('guide_name');
            $table->dropColumn('guide_phone');
            $table->dropColumn('guide_price');
        });
    }
};

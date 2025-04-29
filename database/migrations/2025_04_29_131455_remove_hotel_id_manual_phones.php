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
        Schema::table('manual_phones', function (Blueprint $table) {
            if (Schema::hasColumn('manual_phones', 'hotel_id')) {
                $table->dropForeign(['hotel_id']);
                $table->dropColumn('hotel_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manual_phones', function (Blueprint $table) {
            $table->foreignId('hotel_id')->nullable()->constrained('hotels');
        });
    }
};

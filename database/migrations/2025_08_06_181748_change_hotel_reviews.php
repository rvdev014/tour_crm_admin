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
        Schema::rename('hotel_reviews', 'reviews');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign('hotel_reviews_hotel_id_foreign');
            $table->dropColumn('hotel_id');

            $table->morphs('reviewable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropMorphs('reviewable');

            $table->foreignId('hotel_id')->constrained('hotels')->onDelete('restrict');
        });

        Schema::rename('reviews', 'hotel_reviews');
    }
};

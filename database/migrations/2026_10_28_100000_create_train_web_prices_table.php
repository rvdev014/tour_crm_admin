<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Fixed seated-train prices shown on the website as "guaranteed tickets". Kept apart from
    // train_tariffs, which drive tour-expense costing and must not change when website prices do.
    public function up(): void
    {
        Schema::create('train_web_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('train_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_city_id')->constrained('cities')->cascadeOnDelete();
            $table->foreignId('to_city_id')->constrained('cities')->cascadeOnDelete();
            $table->decimal('price_economy', 14, 2)->nullable();
            $table->decimal('price_business', 14, 2)->nullable();
            $table->decimal('price_vip', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['train_id', 'from_city_id', 'to_city_id']);
        });

        Schema::table('train_requests', function (Blueprint $table) {
            $table->boolean('is_guaranteed')->default(false);
            $table->foreignId('train_web_price_id')->nullable()->constrained('train_web_prices')->nullOnDelete();
            $table->string('train_class')->nullable();
            $table->decimal('price_uzs', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('train_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('train_web_price_id');
            $table->dropColumn(['is_guaranteed', 'train_class', 'price_uzs']);
        });

        Schema::dropIfExists('train_web_prices');
    }
};

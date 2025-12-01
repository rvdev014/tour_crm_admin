<?php

use App\Enums\CurrencyEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tours', function(Blueprint $table) {
            $table->string('price_currency', 5)->default(CurrencyEnum::UZS->value)->nullable();
            $table->string('guide_price_currency', 5)->default(CurrencyEnum::UZS->value)->nullable();
        });
        Schema::table('tour_day_expenses', function(Blueprint $table) {
            $table->string('price_currency', 5)->default(CurrencyEnum::UZS->value)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function(Blueprint $table) {
            //
        });
    }
};

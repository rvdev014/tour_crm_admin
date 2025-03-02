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
        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->dropColumn('hotel_checkout_time');
            $table->dateTime('hotel_checkout_date_time')->nullable()->after('hotel_checkin_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->dropColumn('hotel_checkout_date_time');
            $table->string('hotel_checkout_time')->nullable()->after('hotel_checkin_time');
        });
    }
};

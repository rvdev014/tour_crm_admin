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
        Schema::rename('hotel_phones', 'manual_phones');

        Schema::table('manual_phones', function (Blueprint $table) {
            $table->bigInteger('manual_id')->after('id')->nullable();
            $table->string('manual_type')->after('manual_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manual_phones', function (Blueprint $table) {
            $table->dropColumn('manual_id');
            $table->dropColumn('manual_type');
        });

        Schema::rename('manual_phones', 'hotel_phones');
    }
};

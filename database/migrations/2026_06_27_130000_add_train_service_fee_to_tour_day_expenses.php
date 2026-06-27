<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->decimal('train_service_fee', 15, 2)->nullable()->after('plane_service_fee');
        });
    }

    public function down(): void
    {
        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->dropColumn('train_service_fee');
        });
    }
};

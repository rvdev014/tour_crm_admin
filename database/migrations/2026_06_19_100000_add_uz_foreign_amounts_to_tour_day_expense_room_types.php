<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_day_expense_room_types', function (Blueprint $table) {
            $table->integer('amount_uz')->default(0)->after('amount');
            $table->integer('amount_foreign')->default(0)->after('amount_uz');
        });
    }

    public function down(): void
    {
        Schema::table('tour_day_expense_room_types', function (Blueprint $table) {
            $table->dropColumn(['amount_uz', 'amount_foreign']);
        });
    }
};

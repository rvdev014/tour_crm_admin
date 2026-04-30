<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_room_types', function (Blueprint $table) {
            $table->renameColumn('amount', 'amount_uz');
        });

        Schema::table('tour_room_types', function (Blueprint $table) {
            $table->integer('amount_foreign')->default(0)->after('amount_uz');
        });
    }

    public function down(): void
    {
        Schema::table('tour_room_types', function (Blueprint $table) {
            $table->dropColumn('amount_foreign');
        });

        Schema::table('tour_room_types', function (Blueprint $table) {
            $table->renameColumn('amount_uz', 'amount');
        });
    }
};

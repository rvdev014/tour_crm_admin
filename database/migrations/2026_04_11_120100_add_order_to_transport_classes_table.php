<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_classes', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('transport_classes', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};

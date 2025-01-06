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
            $table->dropColumn('train_class');
            $table->string('train_name')->nullable();
            $table->string('train_class_economy')->nullable();
            $table->string('train_class_vip')->nullable();
            $table->string('train_class_second')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->string('train_class')->nullable();
            $table->dropColumn('train_name');
            $table->dropColumn('train_class_economy');
            $table->dropColumn('train_class_vip');
            $table->dropColumn('train_class_second');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_prices', function (Blueprint $table) {
            $table->dropUnique(['route_id', 'transport_type']);
            $table->dropColumn('transport_type');
            $table->foreignId('transport_class_id')->after('route_id')->constrained('transport_classes')->cascadeOnDelete();
            $table->unique(['route_id', 'transport_class_id']);
        });

        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('transport_class_id')->nullable()->after('route_id');
            $table->foreign('transport_class_id')->references('id')->on('transport_classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->dropForeign(['transport_class_id']);
            $table->dropColumn('transport_class_id');
        });

        Schema::table('route_prices', function (Blueprint $table) {
            $table->dropUnique(['route_id', 'transport_class_id']);
            $table->dropForeign(['transport_class_id']);
            $table->dropColumn('transport_class_id');
            $table->integer('transport_type')->default(0);
            $table->unique(['route_id', 'transport_type']);
        });
    }
};

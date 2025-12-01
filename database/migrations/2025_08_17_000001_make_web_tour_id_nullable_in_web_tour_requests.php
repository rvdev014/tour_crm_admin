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
        Schema::table('web_tour_requests', function (Blueprint $table) {
            $table->dropForeign(['web_tour_id']);
            $table->bigInteger('web_tour_id')->unsigned()->nullable()->change();
            $table->foreign('web_tour_id')->references('id')->on('web_tours')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_tour_requests', function (Blueprint $table) {
            $table->dropForeign(['web_tour_id']);
            $table->bigInteger('web_tour_id')->unsigned()->nullable(false)->change();
            $table->foreign('web_tour_id')->references('id')->on('web_tours')->onDelete('restrict');
        });
    }
};
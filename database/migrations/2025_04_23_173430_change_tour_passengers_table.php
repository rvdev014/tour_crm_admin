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
        Schema::create('tour_groups', function (Blueprint $table) {
            $table->id();
            $table->integer('order')->nullable();
            $table->string('name')->nullable();
            $table->integer('type')->nullable();
            $table->bigInteger('tour_id')->index();
            $table->foreign('tour_id')->references('id')->on('tours')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::table('tour_passengers', function (Blueprint $table) {
            $table->bigInteger('tour_id')->nullable()->change();

            $table->bigInteger('tour_group_id')->nullable()->after('tour_id');
            $table->foreign('tour_group_id')->references('id')->on('tour_groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_passengers', function (Blueprint $table) {
            $table->dropForeign(['tour_group_id']);
            $table->dropColumn('tour_group_id');
            $table->bigInteger('tour_id')->change();
        });

        Schema::dropIfExists('tour_groups');
    }
};

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
        Schema::table('tour_hotel_room_types', function (Blueprint $table) {
            $table->dropForeign(['hotel_room_type_id']);
            $table->dropColumn('hotel_room_type_id');

            $table->bigInteger('room_type_id')->nullable()->unsigned();
            $table->foreign('room_type_id')->references('id')->on('room_types');//->onDelete('cascade');
        });
        Schema::rename('tour_hotel_room_types', 'tour_room_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_room_types', function (Blueprint $table) {
            $table->dropForeign(['room_type_id']);
            $table->dropColumn('room_type_id');

            $table->bigInteger('hotel_room_type_id')->unsigned();
            $table->foreign('hotel_room_type_id')->references('id')->on('hotel_room_types');//->onDelete('cascade');
        });
        Schema::rename('tour_room_types', 'tour_hotel_room_types');
    }
};

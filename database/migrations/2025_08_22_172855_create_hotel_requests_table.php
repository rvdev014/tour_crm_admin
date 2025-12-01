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
        Schema::create('hotel_requests', function (Blueprint $table) {
            $table->id();
            $table->dateTime('checkin_time');
            $table->dateTime('checkout_time');
            $table->foreignId('room_type_id')->constrained('room_types');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('hotel_id')->constrained('hotels');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_requests');
    }
};

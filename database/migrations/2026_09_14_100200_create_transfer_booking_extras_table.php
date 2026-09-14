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
        // Keyed to the booking (not to a single leg): an extra like a child
        // seat isn't specific to one direction of a round trip, and this
        // sidesteps the "charged once or twice on a return trip" ambiguity
        // outright — it's billed once per booking.
        Schema::create('transfer_booking_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_extra_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 8, 2);
            $table->decimal('total_price', 12, 2);

            $table->unique(['transfer_booking_id', 'transfer_extra_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_booking_extras');
    }
};

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
        Schema::table('transport_classes', function (Blueprint $table) {
            $table->decimal('passenger_capacity', 8, 2)->nullable();
            $table->decimal('luggage_capacity', 8, 2)->nullable();
            $table->decimal('waiting_time_included', 8, 2)->nullable();
            $table->boolean('meeting_with_place')->default(false);
            $table->boolean('non_refundable_rate')->default(false);
            $table->string('vehicle_example')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transport_classes', function (Blueprint $table) {
            $table->dropColumn([
                'passenger_capacity',
                'luggage_capacity',
                'waiting_time_included',
                'meeting_with_place',
                'non_refundable_rate',
                'vehicle_example'
            ]);
        });
    }
};

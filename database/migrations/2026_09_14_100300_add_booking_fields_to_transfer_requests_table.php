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
        // Nullable/defaulted throughout — existing rows created by the old
        // create -> pick-class -> book wizard are left with
        // transfer_booking_id = null and are unaffected.
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->foreignId('transfer_booking_id')->nullable()
                ->after('parent_id')
                ->constrained()->cascadeOnDelete();
            $table->string('direction')->nullable()->after('transfer_booking_id');
            $table->unsignedSmallInteger('vehicle_count')->default(1)->after('direction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transfer_booking_id');
            $table->dropColumn(['direction', 'vehicle_count']);
        });
    }
};

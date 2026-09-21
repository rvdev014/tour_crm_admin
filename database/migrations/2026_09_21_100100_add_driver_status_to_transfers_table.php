<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            // Intentionally NO default and NO backfill: NULL means "the driver has not acted yet" and is
            // read as Assigned (Transfer::effectiveDriverStatus()). A default would stamp every historical
            // transfer — including completed and rejected ones — as "Assigned".
            $table->string('driver_status', 32)->nullable();
            $table->timestamp('driver_status_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['driver_status', 'driver_status_updated_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_driver_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            // Who changed it; NULL when an operator/system did. Keep the log if a driver is deleted.
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            // driver | admin | system
            $table->string('source', 16);
            $table->timestamps();

            $table->index(['transfer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_driver_status_logs');
    }
};

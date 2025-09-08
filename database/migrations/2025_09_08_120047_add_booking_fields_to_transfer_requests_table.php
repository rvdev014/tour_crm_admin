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
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->boolean('is_sample_baggage')->default(false);
            $table->integer('baggage_count')->nullable();
            $table->string('terminal_name')->nullable();
            $table->string('text_on_sign')->nullable();
            $table->boolean('activate_flight_tracking')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropColumn([
                'is_sample_baggage',
                'baggage_count',
                'terminal_name',
                'text_on_sign',
                'activate_flight_tracking'
            ]);
        });
    }
};

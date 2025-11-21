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
            $table->unsignedBigInteger('status_updated_by')->nullable();
            $table->foreign('status_updated_by')->references('id')->on('users')->onDelete('set null');
        });
        
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('status_updated_by')->nullable();
            $table->foreign('status_updated_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('hotel_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('status_updated_by')->nullable();
            $table->foreign('status_updated_by')->references('id')->on('users')->onDelete('set null');
        });
        
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('status_updated_by')->nullable();
            $table->foreign('status_updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

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
            $table->dropColumn('transport_class');
        });
        
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->integer('transport_class')->nullable()->after('passengers_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropColumn('transport_class');
        });
        
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->string('transport_class')->nullable()->after('passengers_count');
        });
    }
};
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
            // Drop foreign key constraints first
            $table->dropForeign(['from_city_id']);
            $table->dropForeign(['to_city_id']);
            
            // Drop the city ID columns
            $table->dropColumn(['from_city_id', 'to_city_id']);
            
            // Add string fields for from and to locations
            $table->string('from')->after('id');
            $table->string('to')->after('from');
            
            // Add distance field
            $table->decimal('distance', 8, 2)->nullable()->after('to');
            
            // Make fio and phone nullable
            $table->string('fio')->nullable()->change();
            $table->string('phone')->nullable()->change();
            
            // Add transport_class_id foreign key
            $table->bigInteger('transport_class_id')->unsigned()->nullable()->after('transport_class');
            $table->foreign('transport_class_id')->references('id')->on('transport_classes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            // Drop the transport_class_id foreign key and column
            $table->dropForeign(['transport_class_id']);
            $table->dropColumn('transport_class_id');
            
            // Drop the string location fields and distance
            $table->dropColumn(['from', 'to', 'distance']);
            
            // Re-add city ID columns with foreign keys
            $table->bigInteger('from_city_id')->unsigned()->after('id');
            $table->foreign('from_city_id')->references('id')->on('cities')->onDelete('restrict');
            
            $table->bigInteger('to_city_id')->unsigned()->after('from_city_id');
            $table->foreign('to_city_id')->references('id')->on('cities')->onDelete('restrict');
            
            // Make fio and phone required again
            $table->string('fio')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
        });
    }
};

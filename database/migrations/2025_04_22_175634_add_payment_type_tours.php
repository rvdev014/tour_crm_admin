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
        Schema::table('tours', function (Blueprint $table) {
            $table->integer('payment_type')->nullable()->comment('Cash, Bank');
            $table->integer('payment_status')->nullable()->comment('Paid, Waiting, Cancelled, Refunded');
            $table->double('transfer_price')->nullable()->comment('Transfer price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('payment_type');
            $table->dropColumn('payment_status');
            $table->dropColumn('transfer_price');
        });
    }
};

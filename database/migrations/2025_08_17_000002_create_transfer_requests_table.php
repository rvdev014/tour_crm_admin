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
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            
            $table->bigInteger('from_city_id')->unsigned();
            $table->foreign('from_city_id')->references('id')->on('cities')->onDelete('restrict');
            
            $table->bigInteger('to_city_id')->unsigned();
            $table->foreign('to_city_id')->references('id')->on('cities')->onDelete('restrict');
            
            $table->dateTime('date_time');
            $table->integer('passengers_count');
            $table->string('transport_class')->nullable();
            $table->string('fio');
            $table->string('phone');
            $table->text('comment')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('payment_card')->nullable();
            $table->string('payment_holder_name')->nullable();
            $table->string('payment_valid_until')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
    }
};
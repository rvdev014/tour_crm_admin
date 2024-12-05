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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->integer('transport_type');
            $table->integer('transport_comfort_level');
            $table->decimal('price');

            $table->bigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies');

            $table->integer('status')->nullable();
            $table->integer('pax')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};

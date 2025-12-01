<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('museum_items', function(Blueprint $table) {
            $table->id();

            $table->integer('museum_id');
            $table->foreign('museum_id')->references('id')->on('museums');

            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price_per_person');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('museum_items');
    }
};

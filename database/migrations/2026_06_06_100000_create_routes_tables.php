<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('route_waypoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(0);
        });

        Schema::create('route_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->integer('transport_type');
            $table->decimal('price', 10, 2);
            $table->unique(['route_id', 'transport_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_prices');
        Schema::dropIfExists('route_waypoints');
        Schema::dropIfExists('routes');
    }
};

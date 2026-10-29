<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The Routes admin section (and the per-route transport prices that hung off it) is gone;
    // drop its tables and the columns that pointed at them.
    public function up(): void
    {
        if (Schema::hasColumn('tour_day_expenses', 'route_id')) {
            Schema::table('tour_day_expenses', function (Blueprint $table) {
                $table->dropForeign(['route_id']);
                $table->dropColumn('route_id');
            });
        }

        if (Schema::hasColumn('transfers', 'route_id')) {
            Schema::table('transfers', function (Blueprint $table) {
                $table->dropColumn('route_id');
            });
        }

        Schema::dropIfExists('route_prices');
        Schema::dropIfExists('route_waypoints');
        Schema::dropIfExists('routes');
    }

    public function down(): void
    {
        // Structure only; the deleted route data can't be restored.
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
            $table->foreignId('transport_class_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->unique(['route_id', 'transport_class_id']);
        });

        Schema::table('tour_day_expenses', function (Blueprint $table) {
            $table->foreignId('route_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('route_id')->nullable();
        });
    }
};

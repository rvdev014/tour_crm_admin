<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_tour_free_prices', function (Blueprint $table) {
            $table->integer('pax_from')->after('web_tour_id');
            $table->integer('pax_to')->after('pax_from');
        });

        DB::statement('UPDATE web_tour_free_prices SET pax_from = pax_count, pax_to = pax_count');

        Schema::table('web_tour_free_prices', function (Blueprint $table) {
            $table->dropColumn('pax_count');
        });
    }

    public function down(): void
    {
        Schema::table('web_tour_free_prices', function (Blueprint $table) {
            $table->integer('pax_count')->after('web_tour_id');
        });

        DB::statement('UPDATE web_tour_free_prices SET pax_count = pax_from');

        Schema::table('web_tour_free_prices', function (Blueprint $table) {
            $table->dropColumn(['pax_from', 'pax_to']);
        });
    }
};

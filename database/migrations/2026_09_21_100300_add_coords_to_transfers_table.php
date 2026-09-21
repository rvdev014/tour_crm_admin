<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            // "lat,lng" text — the same convention as transfer_requests.from_coords / to_coords, so
            // GeoDistance::parseCoords() reads both. Lets the driver cabinet open an exact pin in
            // Yandex Maps instead of a text search.
            $table->string('from_coords')->nullable();
            $table->string('to_coords')->nullable();
        });

        // Transfers already created from an API request never got the coordinates copied over.
        DB::statement(<<<'SQL'
            UPDATE transfers t
            SET from_coords = tr.from_coords,
                to_coords = tr.to_coords
            FROM transfer_requests tr
            WHERE t.transfer_request_id = tr.id
              AND (tr.from_coords IS NOT NULL OR tr.to_coords IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['from_coords', 'to_coords']);
        });
    }
};

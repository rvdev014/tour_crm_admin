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
            // The client's phone, so the driver can reach them. Stored as typed/collected — the driver
            // cabinet derives call / WhatsApp / Telegram links from it (App\Support\ClientContact).
            $table->string('client_phone', 32)->nullable();
        });

        // The website collects a phone when a customer books (transfer_requests.phone), but it was never
        // copied onto the Transfer that the booking becomes. Fill in the ones that already exist.
        DB::statement(<<<'SQL'
            UPDATE transfers t
            SET client_phone = tr.phone
            FROM transfer_requests tr
            WHERE t.transfer_request_id = tr.id
              AND tr.phone IS NOT NULL
              AND tr.phone <> ''
        SQL);
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('client_phone');
        });
    }
};

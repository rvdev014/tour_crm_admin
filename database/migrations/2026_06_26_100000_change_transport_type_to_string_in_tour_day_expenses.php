<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tour_day_expenses ALTER COLUMN transport_type TYPE varchar(255) USING transport_type::varchar');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tour_day_expenses ALTER COLUMN transport_type TYPE integer USING transport_type::integer');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cast existing integer values to text (preserves 1, 2, etc.)
        DB::statement('ALTER TABLE transfers ALTER COLUMN transport_type TYPE varchar(255) USING transport_type::varchar');
    }

    public function down(): void
    {
        // Only safe if all values are numeric
        DB::statement('ALTER TABLE transfers ALTER COLUMN transport_type TYPE integer USING transport_type::integer');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE web_tours ALTER COLUMN photos TYPE jsonb USING photos::jsonb');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE web_tours ALTER COLUMN photos TYPE json USING photos::json');
    }
};

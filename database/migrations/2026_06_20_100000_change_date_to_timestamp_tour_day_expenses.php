<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: change date column to timestamp (preserves existing date values at midnight)
        DB::statement('ALTER TABLE tour_day_expenses ALTER COLUMN date TYPE timestamp USING date::timestamp');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tour_day_expenses ALTER COLUMN date TYPE date USING date::date');
    }
};

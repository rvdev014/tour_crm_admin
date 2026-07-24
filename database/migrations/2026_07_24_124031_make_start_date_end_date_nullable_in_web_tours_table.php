<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Free-priced (pax-based) web tours are on-demand and have no fixed
        // dates; the admin form never collected these fields either, which
        // made every web tour creation fail against the NOT NULL constraint.
        DB::statement('ALTER TABLE web_tours ALTER COLUMN start_date DROP NOT NULL');
        DB::statement('ALTER TABLE web_tours ALTER COLUMN end_date DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE web_tours ALTER COLUMN start_date SET NOT NULL');
        DB::statement('ALTER TABLE web_tours ALTER COLUMN end_date SET NOT NULL');
    }
};

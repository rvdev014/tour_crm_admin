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
        // The 2026_06_19 migration split `amount` into `amount_uz` / `amount_foreign`
        // but left the legacy `amount` column NOT NULL. The app only writes the
        // uz/foreign columns now, so every corporate expense room-type insert
        // violated the NOT NULL constraint on `amount`.
        DB::statement('ALTER TABLE tour_day_expense_room_types ALTER COLUMN amount DROP NOT NULL');
        DB::statement('ALTER TABLE tour_day_expense_room_types ALTER COLUMN amount SET DEFAULT 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('UPDATE tour_day_expense_room_types SET amount = 0 WHERE amount IS NULL');
        DB::statement('ALTER TABLE tour_day_expense_room_types ALTER COLUMN amount SET NOT NULL');
        DB::statement('ALTER TABLE tour_day_expense_room_types ALTER COLUMN amount DROP DEFAULT');
    }
};

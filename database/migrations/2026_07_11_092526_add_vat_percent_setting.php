<?php

use App\Enums\DefaultSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill with the rate that was previously hardcoded (12%), so making
        // it configurable doesn't silently change behavior for existing hotels.
        DB::table('settings')->insertOrIgnore([
            'key' => DefaultSettings::VAT_PERCENT->value,
            'value' => '12',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', DefaultSettings::VAT_PERCENT->value)->delete();
    }
};

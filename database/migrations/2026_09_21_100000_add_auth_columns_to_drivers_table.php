<?php

use App\Support\PhoneNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // NULL password = the driver has no cabinet access.
            $table->string('password')->nullable();
            // Lookup key for cabinet login. Derived from `phone` by PhoneNormalizer (Driver::saving).
            $table->string('phone_normalized', 32)->nullable();
            $table->rememberToken();
            $table->boolean('is_active')->default(true);
            $table->string('locale', 2)->nullable();
            $table->timestamp('last_login_at')->nullable();
        });

        $this->backfillPhoneNormalized();

        // Partial index: rows without a usable phone (NULL) are exempt from uniqueness.
        // Laravel 10's Blueprint has no partial-index support, hence raw SQL.
        DB::statement(
            'CREATE UNIQUE INDEX drivers_phone_normalized_unique ON drivers (phone_normalized) WHERE phone_normalized IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS drivers_phone_normalized_unique');

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'phone_normalized',
                'remember_token',
                'is_active',
                'locale',
                'last_login_at',
            ]);
        });
    }

    /**
     * Fill phone_normalized for existing drivers.
     *
     * Numbers the normalizer rejects, and numbers shared by several drivers, are left NULL — we do
     * not guess which of two duplicate records is the "real" one. Both stay without cabinet login
     * until an operator fixes the phone in the CRM. Affected ids are written to the log.
     */
    private function backfillPhoneNormalized(): void
    {
        $byNormalized = [];
        $invalid = [];

        foreach (DB::table('drivers')->select('id', 'phone')->orderBy('id')->get() as $driver) {
            $normalized = PhoneNormalizer::uz($driver->phone);

            if ($normalized === null) {
                if (filled($driver->phone)) {
                    $invalid[] = $driver->id;
                }

                continue;
            }

            $byNormalized[$normalized][] = $driver->id;
        }

        $duplicates = [];

        foreach ($byNormalized as $normalized => $ids) {
            if (count($ids) > 1) {
                $duplicates[$normalized] = $ids;

                continue;
            }

            DB::table('drivers')->where('id', $ids[0])->update(['phone_normalized' => $normalized]);
        }

        if ($invalid !== []) {
            Log::warning('drivers.phone_normalized: unusable phone, left NULL', ['driver_ids' => $invalid]);
        }

        foreach ($duplicates as $normalized => $ids) {
            Log::warning('drivers.phone_normalized: duplicate phone, left NULL for all', [
                'phone' => $normalized,
                'driver_ids' => $ids,
            ]);
        }
    }
};

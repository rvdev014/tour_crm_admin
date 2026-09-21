<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Models\Driver;
use App\Models\Transfer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single source of "which transfers belong to this driver" for the driver cabinet.
 * Every cabinet route resolves transfers through here, so ownership cannot be forgotten per-route.
 */
class DriverTransferQuery
{
    /**
     * Columns a driver is allowed to see. This is a whitelist on purpose: prices (price, total_price,
     * sell_price*, buy_price*), old_values, send_username and the client company are never even
     * fetched from the database, so they cannot leak through a template. Add columns deliberately.
     */
    public const COLUMNS = [
        'id',
        'number',
        'status',
        'date_time',
        'route',
        'from',
        'to',
        'from_coords',
        'to_coords',
        'place_of_submission',
        'location_details',
        'from_city_id',
        'to_city_id',
        'pax',
        'nameplate',
        'mark',
        'transport_type',
        'comment',
        'passenger',
        'driver_ids',
        'driver_status',
        'driver_status_updated_at',
    ];

    /**
     * Transfers of this driver, restricted to the whitelisted columns.
     *
     * @return Builder<Transfer>
     */
    public static function forDriver(Driver $driver): Builder
    {
        return self::owned($driver)->select(self::COLUMNS);
    }

    /**
     * How many transfers the driver has on each day of [$from, $to] (inclusive), keyed "Y-m-d".
     * Days with no transfers are absent.
     *
     * @return array<string, int>
     */
    public static function countPerDay(Driver $driver, CarbonInterface $from, CarbonInterface $to): array
    {
        return self::owned($driver)
            ->whereBetween('date_time', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('date_time::date as day, count(*) as total')
            ->groupByRaw('date_time::date')
            ->pluck('total', 'day')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @return Builder<Transfer>
     */
    private static function owned(Driver $driver): Builder
    {
        return Transfer::query()
            ->whereNotNull('driver_ids')
            // driver_ids is a varchar column holding a JSON array. Production stores the ids as
            // STRINGS (["2","6"]); Postgres `'["2"]'::jsonb @> '2'` is FALSE while `@> '"2"'` is TRUE,
            // so the (string) cast is what makes this return anything at all. The int form is kept
            // for any rows written as [2].
            ->where(fn (Builder $q) => $q
                ->whereJsonContains('driver_ids', (string) $driver->getKey())
                ->orWhereJsonContains('driver_ids', $driver->getKey()))
            // Same set the Telegram bot notifies on (Confirmed), plus Done as read-only history.
            // Never New (may still change) or Rejected (cancelled).
            ->whereIn('status', [ExpenseStatus::Confirmed->value, ExpenseStatus::Done->value]);
    }
}

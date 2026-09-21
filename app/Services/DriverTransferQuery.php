<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Models\Driver;
use App\Models\Transfer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The single source of "which transfers may this cabinet account see" for the driver cabinet.
 * Every cabinet route resolves transfers through here, so ownership cannot be forgotten per-route.
 *
 * A driver sees only transfers they are assigned to. A dispatcher sees every transfer (including ones
 * with no driver yet). Both see only Confirmed and Done, and both are restricted to COLUMNS.
 */
class DriverTransferQuery
{
    /**
     * Columns a cabinet account is allowed to see. This is a whitelist on purpose: prices (price,
     * total_price, sell_price*, buy_price*), old_values, send_username and the client company are never
     * even fetched from the database, so they cannot leak through a template — not even to a
     * dispatcher. Add columns deliberately.
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
        // The client's phone. Not a price, but personal data: the PRESENTER decides who gets it
        // (dispatchers always, drivers only while the trip is open) — see DriverTransferController.
        'client_phone',
        'driver_ids',
        'driver_name',
        'driver_phone',
        'driver_status',
        'driver_status_updated_at',
    ];

    /**
     * Transfers this account may see, restricted to the whitelisted columns.
     *
     * @return Builder<Transfer>
     */
    public static function forDriver(Driver $viewer): Builder
    {
        return self::owned($viewer)->select(self::COLUMNS);
    }

    /**
     * The ids of the transfers this account may see, as a subquery — for scoping other tables (expenses)
     * to exactly the same set, so "visible transfer" has one definition.
     *
     * @return Builder<Transfer>
     */
    public static function idsFor(Driver $viewer): Builder
    {
        return self::owned($viewer)->select('transfers.id');
    }

    /**
     * How many transfers this account can see on each day of [$from, $to] (inclusive), keyed "Y-m-d".
     * Days with no transfers are absent.
     *
     * @return array<string, int>
     */
    public static function countPerDay(Driver $viewer, CarbonInterface $from, CarbonInterface $to): array
    {
        return self::owned($viewer)
            ->whereBetween('date_time', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('date_time::date as day, count(*) as total')
            ->groupByRaw('date_time::date')
            ->pluck('total', 'day')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * The people assigned to the given transfers, in ONE query, keyed by id. Only contact columns are
     * read — never password, chat_id or phone_normalized.
     *
     * Meant for dispatchers only. A driver must not be handed this: a transfer can have two drivers
     * (["2","6"]) and one must not see the other's phone.
     *
     * @param  iterable<Transfer>  $transfers
     * @return Collection<int, Driver>
     */
    public static function driversFor(iterable $transfers): Collection
    {
        $ids = collect($transfers)
            ->flatMap(fn (Transfer $t) => (array) $t->driver_ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Driver::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'phone', 'car_number', 'car_model'])
            ->keyBy('id');
    }

    /**
     * How many transfers each driver has on a day, keyed by driver id. Counted in PHP from the decoded
     * driver_ids arrays: a transfer with two drivers counts once for each, and there is no
     * jsonb_array_elements gymnastics to get wrong on a varchar column.
     *
     * @return array<int, int>
     */
    public static function tripsPerDriverOn(Driver $viewer, CarbonInterface $day): array
    {
        $counts = [];

        self::owned($viewer)
            ->whereDate('date_time', $day->toDateString())
            ->get(['id', 'driver_ids'])
            ->each(function (Transfer $transfer) use (&$counts) {
                foreach ((array) $transfer->driver_ids as $id) {
                    if (is_numeric($id)) {
                        $counts[(int) $id] = ($counts[(int) $id] ?? 0) + 1;
                    }
                }
            });

        return $counts;
    }

    /**
     * @return Builder<Transfer>
     */
    private static function owned(Driver $viewer): Builder
    {
        // Same set the Telegram bot notifies on (Confirmed), plus Done as read-only history.
        // Never New (may still change) or Rejected (cancelled).
        $query = Transfer::query()
            ->whereIn('status', [ExpenseStatus::Confirmed->value, ExpenseStatus::Done->value]);

        if ($viewer->isDispatcher()) {
            return $query;
        }

        return $query
            ->whereNotNull('driver_ids')
            // driver_ids is a varchar column holding a JSON array. Production stores the ids as
            // STRINGS (["2","6"]); Postgres `'["2"]'::jsonb @> '2'` is FALSE while `@> '"2"'` is TRUE,
            // so the (string) cast is what makes this return anything at all. The int form is kept
            // for any rows written as [2].
            ->where(fn (Builder $q) => $q
                ->whereJsonContains('driver_ids', (string) $viewer->getKey())
                ->orWhereJsonContains('driver_ids', $viewer->getKey()));
    }
}

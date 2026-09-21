<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverExpense;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which expenses a cabinet account may see. Same principle as DriverTransferQuery: every cabinet route
 * goes through here, so ownership is not re-implemented (and forgotten) per route.
 *
 * - a DRIVER sees only the expenses THEY entered, and only on trips they can see — a co-driver on a
 *   shared trip does not see the other's expenses;
 * - a DISPATCHER sees every expense on every visible trip.
 */
class DriverExpenseQuery
{
    /**
     * @return Builder<TransferDriverExpense>
     */
    public static function forViewer(Driver $viewer): Builder
    {
        $query = TransferDriverExpense::query()
            ->whereIn('transfer_id', DriverTransferQuery::idsFor($viewer));

        return $viewer->isDispatcher() ? $query : $query->where('driver_id', $viewer->getKey());
    }

    /**
     * @return Builder<TransferDriverExpense>
     */
    public static function forTransfer(Driver $viewer, Transfer $transfer): Builder
    {
        return self::forViewer($viewer)
            ->where('transfer_id', $transfer->getKey())
            ->with('addedBy:id,name,role')
            ->orderBy('id');
    }
}

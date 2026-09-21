<?php

namespace App\Services;

use App\Enums\DriverTransferStatus;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverStatusLog;
use DomainException;
use Illuminate\Support\Facades\DB;

class DriverTransferStatusService
{
    /**
     * Move a transfer to a new driver status and record it in the log.
     *
     * Returns false when nothing changed (the transfer is already in $to) so a double-tap on a flaky
     * connection is harmless. A driver ($source = driver) may only step forward by one status; admins
     * and the system may set any status, e.g. to undo a mis-tap.
     *
     * Writes with updateQuietly() ON PURPOSE. TransferObserver::updating() overwrites `old_values` and
     * TransferObserver::updated() sends the driver a "♻️ Обновлено" Telegram message whenever a
     * Confirmed transfer is saved — a driver tapping a status button must trigger neither. It also
     * means `old_values` keeps the snapshot of the last OPERATOR edit, which is what the next
     * operator edit should be diffed against.
     *
     * @throws DomainException when a driver attempts a non-sequential transition
     */
    public static function apply(
        Transfer $transfer,
        DriverTransferStatus $to,
        ?Driver $by,
        string $source = TransferDriverStatusLog::SOURCE_DRIVER,
    ): bool {
        return DB::transaction(function () use ($transfer, $to, $by, $source) {
            // Re-read under a row lock so two simultaneous taps cannot both pass the check and
            // write duplicate log rows. Reads the raw column through the query builder (not the
            // model, whose enum cast would apply) and only that one column: the caller's model may
            // be a price-free whitelisted select (DriverTransferQuery) and must stay that way.
            $current = DriverTransferStatus::tryFrom(
                (string) DB::table('transfers')->where('id', $transfer->getKey())->lockForUpdate()->value('driver_status')
            ) ?? DriverTransferStatus::Assigned;

            if ($current === $to) {
                return false;
            }

            if ($source === TransferDriverStatusLog::SOURCE_DRIVER && ! $current->canAdvanceTo($to)) {
                throw new DomainException("Driver cannot move a transfer from {$current->value} to {$to->value}");
            }

            $now = now();

            $transfer->updateQuietly([
                'driver_status' => $to,
                'driver_status_updated_at' => $now,
            ]);

            TransferDriverStatusLog::create([
                'transfer_id' => $transfer->getKey(),
                'driver_id' => $by?->getKey(),
                'from_status' => $current,
                'to_status' => $to,
                'source' => $source,
            ]);

            return true;
        });
    }
}

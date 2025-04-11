<?php

namespace App\Observers;

use App\Models\Transfer;
use App\Enums\ExpenseStatus;
use App\Services\TourService;

class TransferObserver
{
    /**
     * Handle the Transfer "created" event.
     */
    public function created(Transfer $transfer): void
    {
//        if ($transfer->status == ExpenseStatus::Confirmed) {
//            TourService::sendTelegramTransfer($transfer->toArray());
//        }
    }

    public function updating(Transfer $transfer): void
    {
        // save all fields except old_values
        $oldValues = $transfer->getOriginal();
        unset($oldValues['old_values']);
        $transfer->old_values = $oldValues;
    }

    /**
     * Handle the Transfer "updated" event.
     */
    public function updated(Transfer $transfer): void
    {
//        if ($transfer->status == ExpenseStatus::Confirmed) {
//            TourService::sendTelegramTransfer($transfer->toArray());
//        }
    }

    /**
     * Handle the Transfer "deleted" event.
     */
    public function deleted(Transfer $transfer): void
    {
        //
    }

    /**
     * Handle the Transfer "restored" event.
     */
    public function restored(Transfer $transfer): void
    {
        //
    }

    /**
     * Handle the Transfer "force deleted" event.
     */
    public function forceDeleted(Transfer $transfer): void
    {
        //
    }
}

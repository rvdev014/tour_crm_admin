<?php

namespace App\Observers;

use App\Enums\ExpenseType;
use App\Models\TourDayExpense;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class TourDayExpenseObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the TourDayExpense "created" event.
     */
    public function created(TourDayExpense $tourDayExpense): void
    {
        if ($tourDayExpense->type === ExpenseType::Transport) {
            Transfer::create($this->changedAttributes($tourDayExpense));
        }
    }

    /**
     * Handle the TourDayExpense "updated" event.
     */
    public function updated(TourDayExpense $tourDayExpense): void
    {
        if ($tourDayExpense->type === ExpenseType::Transport) {
            $transfer = Transfer::where('tour_day_expense_id', $tourDayExpense->id)->first();
            if ($transfer) {
                $transfer->update($this->changedAttributes($tourDayExpense));
            } else {
                Transfer::create($this->changedAttributes($tourDayExpense));
            }
        }
    }

    /**
     * Handle the TourDayExpense "deleted" event.
     */
    public function deleted(TourDayExpense $tourDayExpense): void
    {
        if ($tourDayExpense->type === ExpenseType::Transport) {
            Transfer::where('tour_day_expense_id', $tourDayExpense->id)->delete();
        }
    }

    /**
     * Handle the TourDayExpense "restored" event.
     */
    public function restored(TourDayExpense $tourDayExpense): void
    {
        //
    }

    /**
     * Handle the TourDayExpense "force deleted" event.
     */
    public function forceDeleted(TourDayExpense $tourDayExpense): void
    {
        //
    }

    public function changedAttributes(TourDayExpense $tourDayExpense): array
    {
        $tour = $tourDayExpense->tourDay?->tour ?? $tourDayExpense->tour;
        $expenseDate = $tourDayExpense->tourDay?->date ?? $tourDayExpense->date;

        $dateTime = null;
        if ($expenseDate) {
            $dateTime = Carbon::parse($expenseDate->format('Y-m-d') . ' ' . ($tourDayExpense->transport_time ?? '00:00:00'));
        }

        return [
            'from_city_id' => $tourDayExpense->tourDay?->city_id ?? $tourDayExpense->city_id,
            'to_city_id' => $tourDayExpense->to_city_id,
            'comment' => $tourDayExpense->comment,
            'company_id' => $tour->company_id,
            'group_number' => $tour->group_number,
            'transport_type' => $tour->transport_type,
            'transport_comfort_level' => $tour->transport_comfort_level,
            'price' => $tourDayExpense->price,
            'status' => $tourDayExpense->status,
            'pax' => $tour->getTotalPax(),
            'tour_day_expense_id' => $tourDayExpense->id,

            'driver_ids' => $tourDayExpense->transport_driver_ids,
            'date_time' => $dateTime,
            'place_of_submission' => $tourDayExpense->transport_place,
        ];
    }
}

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
        $expenseDate = $tourDayExpense->tourDay->date->format('Y-m-d');
        $dateTime = Carbon::parse($expenseDate . ' ' . ($tourDayExpense->transport_time ?? '00:00:00'));

        return [
            'from_city_id' => $tourDayExpense->tourDay->city_id,
            'to_city_id' => $tourDayExpense->to_city_id,
            'comment' => $tourDayExpense->comment,
            'company_id' => $tourDayExpense->tourDay->tour->company_id,
            'group_number' => $tourDayExpense->tourDay->tour->group_number,
            'transport_type' => $tourDayExpense->tourDay->tour->transport_type,
            'transport_comfort_level' => $tourDayExpense->tourDay->tour->transport_comfort_level,
            'price' => $tourDayExpense->price,
            'status' => $tourDayExpense->status,
            'pax' => $tourDayExpense->tourDay->tour->pax,
            'tour_day_expense_id' => $tourDayExpense->id,

            'driver' => $tourDayExpense->transport_driver,
            'date_time' => $dateTime,
            'place_of_submission' => $tourDayExpense->transport_place,
        ];
    }
}

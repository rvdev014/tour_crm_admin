<?php

namespace App\Services;

use App\Enums\ExpenseType;
use App\Mail\HotelMail;
use App\Mail\RestaurantMail;
use App\Models\Hotel;
use App\Models\Restaurant;
use App\Models\Tour;
use App\Models\TourDayExpense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

class MailService
{
    public static function checkExpenseType(TourDayExpense $expense, $type = 'restaurants'): bool
    {
        if ($type == 'restaurants') {
            return in_array($expense->type, [ExpenseType::Lunch, ExpenseType::Dinner]);
        }
        return $expense->type == ExpenseType::Hotel;
    }

    public static function getExpensesDataForMail(Tour $tour, $type = 'restaurants', $isCorporate = false): array
    {
        /** @var Collection<TourDayExpense> $expenses */
        $expenses = ExpenseService::getAllExpenses($tour)->filter(fn($expense) => self::checkExpenseType($expense, $type));

        $expensesData = [];
        foreach ($expenses as $expense) {
            $date = $isCorporate ? $expense->date : $expense->tourDay->date;
            $expensesData[$date->format('Y-m-d')] = $expense;
        }

        return $expensesData;
    }

    public static function sendMailRestaurants(Tour $tour): void
    {
        /** @var Collection<TourDayExpense> $expensesData */
        $expensesData = self::getExpensesDataForMail($tour, 'restaurants', $tour->isCorporate());
        foreach ($expensesData as $date => $expense) {
            if (!$expense->restaurant_id) {
                continue;
            }

            /** @var Restaurant $restaurant */
            $restaurant = Restaurant::find($expense->restaurant_id);
            if (!empty($restaurant->email)) {
                Mail::to($restaurant->email)->send(
                    new RestaurantMail($date, $expense, $tour->getTotalPax())
                );
            }
        }
    }

    public static function sendMailHotels(Tour $tour): void
    {
        /** @var Collection<TourDayExpense> $expensesData */
        $expensesData = self::getExpensesDataForMail($tour, 'hotels', $tour->isCorporate());
        foreach ($expensesData as $date => $expense) {
            if (!$expense->hotel_id) {
                continue;
            }

            /** @var Hotel $hotel */
            $hotel = Hotel::find($expense->hotel_id);
            if (!empty($hotel->email)) {
                Mail::to($hotel->email)->send(
                    new HotelMail($date, $expense, $tour->getTotalPax())
                );
            }
        }
    }
}

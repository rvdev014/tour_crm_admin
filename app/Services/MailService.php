<?php

namespace App\Services;

use Carbon\Carbon;
use App\Enums\ExpenseType;
use App\Mail\HotelMail;
use App\Mail\RestaurantMail;
use App\Models\Hotel;
use App\Models\Restaurant;
use App\Models\Tour;
use App\Models\TourDayExpense;
use Illuminate\Support\Facades\File;
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
        $tempDir = ExportService::getTempDir('hotel_reports');
        $hotelsData = ExportHotelService::getHotelsData($tour);

        /** @var Collection<TourDayExpense> $expensesData */
        $expensesData = self::getExpensesDataForMail($tour, 'hotels', $tour->isCorporate());
        foreach ($expensesData as $date => $expense) {
            if (!$expense->hotel_id) {
                continue;
            }

            /** @var Hotel $hotel */
            $hotel = Hotel::find($expense->hotel_id);
            if (!empty($hotel->email)) {
                $hotelItem = $hotelsData->get($expense->id);
                if (!$hotelItem) {
                    continue; // Skip if hotel data is not available
                }

                $firstArrivalTime = Carbon::parse(collect($hotelItem['arrivals'])->first())->format('d-m');
                $lastDepartureTime = Carbon::parse(collect($hotelItem['departures'])->last())->format('d-m');

                $rooming = $hotelItem['rooming']->map(function ($value, $key) {
                    return $value . strtolower($key);
                })->implode('/');

                $subject = "$tour->group_number | $firstArrivalTime-$lastDepartureTime | $rooming";
                $mailable = new HotelMail(
                    $subject,
                    $date,
                    $expense,
                    $tour->getTotalPax(),
                    $hotelItem
                );
                Mail::to($hotel->email)->send($mailable);
            }
        }

        register_shutdown_function(fn() => File::deleteDirectory($tempDir));
    }
}

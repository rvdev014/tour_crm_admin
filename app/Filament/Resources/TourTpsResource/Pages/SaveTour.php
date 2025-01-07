<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\ExpenseType;
use App\Mail\HotelMail;
use App\Mail\RestaurantMail;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\Museum;
use App\Models\Restaurant;
use App\Models\Show;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

trait SaveTour
{
    protected function getExpensesData($days, $totalPax)
    {
        return $days->flatMap(fn($day) => $day['expenses'])->map(function ($expense) use ($totalPax) {
            switch ($expense['type']) {
                case ExpenseType::Museum->value:
                    /** @var Museum $museum */
                    $museum = Museum::query()->find($expense['museum_item_id'] ?? $expense['museum_id']);
                    if ($museum) {
                        $expense['price'] = $museum->price_per_person * $totalPax;
                    }
                    return $expense;

                case ExpenseType::Lunch->value:
                case ExpenseType::Dinner->value:
                    /** @var Restaurant $restaurant */
                    $restaurant = Restaurant::query()->find($expense['restaurant_id']);
                    if ($restaurant) {
                        $expense['price'] = $restaurant->price_per_person * $totalPax;
                    }
                    return $expense;

                case ExpenseType::Show->value:
                    /** @var Show $show */
                    $show = Show::query()->find($expense['show_id']);
                    if ($show) {
                        $expense['price'] = $show->price_per_person * $totalPax;
                    }
                    return $expense;

                default:
                    return $expense;
            }
        });
    }

    protected function getRoomingAmounts($data): Collection
    {
        return collect($data)
            ->filter(fn($value, $key) => str_starts_with($key, 'hotel_type_'))
            ->mapWithKeys(fn($value, $key) => [(int)str_replace('hotel_type_', '', $key) => $value]);
    }

    protected function getHotelExpensesTotal($hotelExpenses, $roomTypeAmounts): int|float
    {
        $hotelExpensesTotal = 0;
        foreach ($hotelExpenses as $hotelExpense) {
            /** @var Hotel $hotel */
            $hotel = Hotel::find($hotelExpense['hotel_id']);
            foreach ($roomTypeAmounts as $roomTypeId => $amount) {
                /** @var HotelRoomType $hotelRoomType */
                $hotelRoomType = $hotel->roomTypes()->where('room_type_id', $roomTypeId)->first();
                if (!$hotelRoomType) {
                    continue;
                }
                $hotelExpensesTotal += $hotelRoomType->price * $amount;
            }
        }
        return $hotelExpensesTotal;
    }

    protected function sendMails($tourData, $days): void
    {
        $hotelsData = [];
        $restaurantsData = [];
        foreach ($days as $day) {
            foreach ($day['expenses'] as $expense) {
                switch ($expense['type']) {
                    case ExpenseType::Hotel->value:
                        $hotelId = $expense['hotel_id'];
                        if ($hotel = Hotel::find($hotelId)) {
                            $hotelsData[$day['date']] = [
                                'hotel' => $hotel,
                                'expense' => $expense,
                            ];
                        }
                        break;
                    case ExpenseType::Lunch->value:
                    case ExpenseType::Dinner->value:
                        $restaurantId = $expense['restaurant_id'];
                        if ($restaurant = Restaurant::find($restaurantId)) {
                            $restaurantsData[$day['date']] = [
                                'restaurant' => $restaurant,
                                'expense' => $expense,
                            ];
                        }
                        break;
                }
            }
        }

        foreach ($hotelsData as $date => $hotelItem) {
            /** @var Hotel $hotel */
            $hotel = $hotelItem['hotel'];
            Mail::to($hotel->email)->send(new HotelMail($date, $hotelItem['expense'], $tourData));
        }

        foreach ($restaurantsData as $date => $restaurantItem) {
            /** @var Restaurant $restaurant */
            $restaurant = $restaurantItem['restaurant'];
            Mail::to($restaurant->email)->send(new RestaurantMail($date, $restaurantItem['expense'], $tourData));
        }
    }
}

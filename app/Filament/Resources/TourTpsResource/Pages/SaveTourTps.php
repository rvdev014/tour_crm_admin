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

trait SaveTourTps
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
}

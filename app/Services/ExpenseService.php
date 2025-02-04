<?php

namespace App\Services;

use App\Enums\ExpenseType;
use App\Models\Company;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\Museum;
use App\Models\MuseumItem;
use App\Models\Restaurant;
use App\Models\Show;
use App\Models\Train;
use Illuminate\Support\Collection;

class ExpenseService
{
    public static function mutateExpense(array $data, $totalPax, $roomTypeAmounts, $companyId = null): array
    {
        $addPercent = TourService::getCompanyAddPercent($companyId);

        switch ($data['type']) {
            case ExpenseType::Hotel->value:
                /** @var Hotel $hotel */
                $hotel = Hotel::query()->find($data['hotel_id']);
                if ($hotel) {
                    $hotelTotal = 0;
                    foreach ($roomTypeAmounts as $roomTypeId => $amount) {
                        $totalNights = $data['hotel_total_nights'] ?? 1;

                        /** @var HotelRoomType $hotelRoomType */
                        $hotelRoomType = $hotel->roomTypes()->where('room_type_id', $roomTypeId)->first();
                        if (!$hotelRoomType || empty($amount)) {
                            continue;
                        }

                        $hotelTotal += $hotelRoomType->getPrice($addPercent) * $amount * $totalNights;
                    }
                    $data['price'] = $hotelTotal;
                }
                return $data;

            case ExpenseType::Museum->value:

                $museumId = $data['museum_id'] ?? null;
                $museumIds = $data['museum_ids'] ?? null;
                $museumItemId = $data['museum_item_id'] ?? null;
                $museumItemIds = $data['museum_item_ids'] ?? null;

                if (!empty($museumItemIds)) {
                    $museumItems = MuseumItem::query()->whereIn('id', $museumItemIds)->get();
                    if ($museumItems->isNotEmpty()) {
                        $data['price'] = $museumItems->sum('price_per_person') * $totalPax;
                    }
                } else {
                    if (!empty($museumIds)) {
                        $museums = Museum::query()->whereIn('id', $museumIds)->get();
                        if ($museums->isNotEmpty()) {
                            $data['price'] = $museums->sum('price_per_person') * $totalPax;
                        }
                    }
                }

                return $data;

            case ExpenseType::Lunch->value:
            case ExpenseType::Dinner->value:
                /** @var Restaurant $restaurant */
                $restaurant = Restaurant::query()->find($data['restaurant_id']);
                if ($restaurant) {
                    $data['price'] = $restaurant->price_per_person * $totalPax;
                }
                return $data;

            case ExpenseType::Show->value:
                /** @var Show $show */
                $show = Show::query()->find($data['show_id']);
                if ($show) {
                    $data['price'] = $show->price_per_person * $totalPax;
                }
                return $data;

            case ExpenseType::Train->value:
                /** @var Train $train */
                $train = Train::query()->find($data['train_id']);
                if ($train) {
                    $trainTariff = $train->tariffs()
                        ->where('from_city_id', $data['city_id'])
                        ->where('to_city_id', $data['to_city_id'])
                        ->first();

                    $totalPrice = 0;
                    $prices = ExpenseService::getTrainPrices($data);
                    dd($prices);

                    $data['price'] = $train->price_per_person * $totalPax;
                }
                return $data;

            default:
                return $data;
        }
    }

    public static function getRoomingAmounts($data): Collection
    {
        return collect($data)
            ->filter(fn($value, $key) => str_starts_with($key, 'room_type_'))
            ->mapWithKeys(fn($value, $key) => [(int)str_replace('room_type_', '', $key) => $value]);
    }

    public static function getTrainPrices($data): Collection
    {
        dd(collect($data)->filter(fn($value, $key) => str_starts_with($key, 'train_class_')));
        return collect($data)
            ->filter(fn($value, $key) => str_starts_with($key, 'train_class_'))
            ->mapWithKeys(fn($value, $key) => [(int)str_replace('train_', '', $key) => $value]);
    }
}

<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Driver;
use App\Models\Country;
use App\Models\HotelPeriod;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\TourStatus;
use App\Models\Company;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\Museum;
use App\Models\MuseumItem;
use App\Models\Restaurant;
use App\Models\Show;
use App\Models\TourRoomType;
use App\Models\Train;
use App\Enums\RoomSeasonType;
use App\Enums\RoomPersonType;
use Illuminate\Support\Collection;

class ExpenseService
{
    public static function mutateExpenses($formState, $isCorporate = false): Collection
    {
        $allExpenses = collect();
        $roomingAmounts = ExpenseService::getRoomingAmounts($formState);

        if ($isCorporate) {
            $totalPax = count($formState['passengers'] ?? []);
            foreach ($formState['expenses'] ?? [] as $expense) {
                $allExpenses->push(
                    ExpenseService::mutateExpense(
                        $expense,
                        $totalPax,
                        $roomingAmounts,
                        $formState['country_id'],
                        $formState['company_id']
                    )
                );
            }
        } else {
            $totalPax = $formState['pax'] + ($formState['leader_pax'] ?? 0);

            $days = collect($formState['days'] ?? []);
            foreach ($days as $day) {
                foreach ($day['expenses'] ?? [] as $expense) {
                    $allExpenses->push(
                        ExpenseService::mutateExpense(
                            $expense,
                            $totalPax,
                            $roomingAmounts,
                            $formState['country_id'],
                            null,
                            $day
                        )
                    );
                }
            }
        }

        return $allExpenses;
    }

    public static function mutateExpense(array $data, $totalPax, $roomAmounts, $countryId, $companyId = null, $day = null): array
    {
        switch ($data['type']) {
            case ExpenseType::Hotel->value:
                /** @var Hotel $hotel */
                $hotel = Hotel::query()->find($data['hotel_id']);
                if ($hotel) {
                    $seasonType = ExpenseService::getSeasonType($hotel, $day ? $day['date'] : $data['date']);
                    $personType = ExpenseService::getPersonType($countryId);

                    $addPercent = TourService::getCompanyAddPercent($companyId);
                    $hotelTotal = 0;
                    foreach ($roomAmounts as $roomTypeId => $amount) {
                        if (empty($amount)) {
                            continue;
                        }

                        $totalNights = $data['hotel_total_nights'] ?? 1;

                        /** @var HotelRoomType $hotelRoomType */
                        $hotelRoomType = $hotel->roomTypes()
                            ->where('room_type_id', $roomTypeId)
                            ->where('season_type', $seasonType)
                            ->first();
                        if (!$hotelRoomType) {
                            continue;
                        }

                        $hotelTotal += $hotelRoomType->getPrice($addPercent, $personType) * $amount * $totalNights;
                    }

                    $data['price'] = $hotelTotal;
                }
                return $data;

            case ExpenseType::Museum->value:

                $museumIds = $data['museum_ids'] ?? null;
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
                        ->where('from_city_id', $day ? $day['city_id'] : $data['city_id'])
                        ->where('to_city_id', $data['to_city_id'])
                        ->first();

                    $totalPrice = 0;
                    $prices = ExpenseService::getTrainPrices($data);
                    foreach ($prices as $trainClass => $amount) {
                        if (isset($trainTariff->$trainClass)) {
                            $totalPrice += $trainTariff->$trainClass * $amount;
                        }
                    }

                    $data['price'] = $totalPrice;
                }
                return $data;

            default:
                return $data;
        }
    }

    public static function getSeasonType(Hotel $hotel, $date): ?RoomSeasonType
    {
        /** @var RoomSeasonType $seasonType */
        $seasonType = CacheService::remember(
            'season_type',
            function() use ($date, $hotel) {
                $hotelDate = Carbon::parse($date);
                /** @var HotelPeriod $currentSeason */
                $currentSeason = $hotel->periods()
                    ->where('start_date', '<=', $hotelDate)
                    ->where('end_date', '>=', $hotelDate)
                    ->first();

                return $currentSeason?->season_type;
            }
        );

        return $seasonType;
    }

    public static function getPersonType($countryId): ?RoomPersonType
    {
        if (!$countryId) {
            return RoomPersonType::Uzbek;
        }

        /** @var Country $country */
        $country = Country::query()->find($countryId);
        if (!$country) {
            return null;
        }

        return $country->name === 'Uzbekistan' ? RoomPersonType::Uzbek : RoomPersonType::Foreign;
    }

    public static function createTourRoomTypes($tourId, $formState): void
    {
        $roomAmounts = ExpenseService::getRoomingAmounts($formState);
        foreach ($roomAmounts as $roomTypeId => $amount) {
            if (empty($amount)) {
                continue;
            }

            TourRoomType::query()->updateOrCreate(
                [
                    'tour_id' => $tourId,
                    'room_type_id' => $roomTypeId
                ],
                [
                    'amount' => $amount,
                ]
            );
        }
    }

    public static function updateTourRoomTypes($tourId, $tourData): void
    {
        $roomAmounts = ExpenseService::getRoomingAmounts($tourData);
        foreach ($roomAmounts as $roomTypeId => $amount) {

            $tourHotelRoomType = TourRoomType::query()
                ->where('tour_id', $tourId)
                ->where('room_type_id', $roomTypeId)
                ->first();

            if ($tourHotelRoomType) {
                if (empty($amount)) {
                    $tourHotelRoomType->delete();
                } else {
                    $tourHotelRoomType->update(['amount' => $amount]);
                }
            } else {
                if (!empty($amount)) {
                    TourRoomType::query()->create([
                        'tour_id' => $tourId,
                        'room_type_id' => $roomTypeId,
                        'amount' => $amount,
                    ]);
                }
            }
        }
    }

    public static function getTourStatus($allExpenses): TourStatus
    {
        $tourStatus = TourStatus::Confirmed;
        foreach ($allExpenses as $expense) {
            $status = $expense['status'] ?? null;
            if ($status == ExpenseStatus::New->value) {
                $tourStatus = TourStatus::NotConfirmed;
                break;
            }
        }

        return $tourStatus;
    }

    public static function getRoomingAmounts($data): Collection
    {
        return collect($data)
            ->filter(fn($value, $key) => str_starts_with($key, 'room_type_'))
            ->mapWithKeys(fn($value, $key) => [str_replace('room_type_', '', $key) => $value]);
    }

    public static function getTrainPrices($data): Collection
    {
        return collect($data)
            ->filter(fn($value, $key) => str_starts_with($key, 'train_class_'))
            ->mapWithKeys(fn($value, $key) => [str_replace('train_', '', $key) => $value]);
    }
}

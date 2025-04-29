<?php

namespace App\Services;

use App\Enums\CurrencyEnum;
use App\Models\TourDayExpense;
use Illuminate\Support\Arr;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\RoomPersonType;
use App\Enums\RoomSeasonType;
use App\Enums\TourStatus;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Hotel;
use App\Models\HotelPeriod;
use App\Models\HotelRoomType;
use App\Models\Museum;
use App\Models\MuseumItem;
use App\Models\Restaurant;
use App\Models\Show;
use App\Models\Tour;
use App\Models\TourDayExpenseRoomType;
use App\Models\TourRoomType;
use App\Models\Train;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExpenseService
{
    public static function getAllExpensesTps(Tour $tour): Collection
    {
        $allExpenses = collect();
        foreach ($tour->days as $day) {
            foreach ($day->expenses as $expense) {
                $allExpenses->push($expense);
            }
        }
        return $allExpenses;
    }

    public static function getAllExpensesCorporate(Tour $tour): Collection
    {
        $allExpenses = collect();
        foreach ($tour->groups as $group) {
            foreach ($group->expenses as $expense) {
                $allExpenses->push($expense);
            }
        }
        return $allExpenses;
    }

    public static function getAllExpensesCorporateBasic($formState): Collection
    {
        $allExpenses = collect();

        $groups = Arr::get($formState, 'groups', []);
        foreach ($groups as $group) {
            $totalPaxGroup = count($group['passengers'] ?? []);
            foreach (Arr::get($group, 'expenses') as $expense) {

                $updatedExpense = ExpenseService::mutateExpense(
                    data: $expense,
                    totalPax: $totalPaxGroup,
                    roomAmounts: ExpenseService::getRoomingAmountsForExpense($expense)
                );

                $allExpenses->push($updatedExpense);
            }
        }

        return $allExpenses;
    }

    public static function getRoomingAmountsForExpense($expense): array
    {
        $roomTypes = collect(Arr::get($expense, 'roomTypes', []));
        $roomingAmounts = [];
        foreach ($roomTypes as $roomType) {
            $roomingAmounts[$roomType['room_type_id']] = $roomType['amount'];
        }
        return $roomingAmounts;
    }

    public static function updateExpensesPricesTps(Tour $tour, $updatedData = [], $withRooming = false): float
    {
        $expensesTotal = 0;
        $updatedData = array_merge($tour->getAttributes(), $updatedData);

        $totalPax = $updatedData['pax'] + ($updatedData['leader_pax'] ?? 0);
        if ($withRooming) {
            $roomingAmounts = ExpenseService::getRoomingAmounts($updatedData);
        } else {
            $roomingAmounts = $tour->roomTypes->mapWithKeys(fn($roomType) => [
                $roomType->room_type_id => $roomType->amount
            ]);
        }

        foreach ($tour->days as $day) {
            foreach ($day->expenses as $expense) {
                $updatedExpense = ExpenseService::mutateExpense(
                    data: $expense->toArray(),
                    totalPax: $totalPax,
                    countryId: $updatedData['country_id'],
                    roomAmounts: $roomingAmounts,
                    day: $day
                );

                $expensePrice = $updatedExpense['price_converted'] ?? $updatedExpense['price'] ?? 0;
                $expense->update(array_merge($updatedExpense, ['price_result' => $expensePrice]));

                $expensesTotal += $expensePrice;
            }
        }

        return $expensesTotal;
    }


    public static function calculateAllExpensesPrice(Collection $allExpenses): float
    {
        $result = 0;
        foreach ($allExpenses as $expense) {
            $result += $expense['price_converted'] ?? $expense['price'];
        }
        return $result;
    }

    public static function mutateExpense(
        array $data,
              $totalPax,
              $countryId = null,
              $roomAmounts = null,
              $companyId = null,
              $day = null
    ): array {
        ExpenseService::convertExpensePrice($data, 'price');

        switch ($data['type']) {
            case ExpenseType::Hotel->value:
                /** @var Hotel $hotel */
                $hotel = Hotel::query()->find($data['hotel_id']);
                if ($hotel) {
                    $seasonType = ExpenseService::getSeasonType($hotel, $day ? $day['date'] : $data['date']);
                    $personType = ExpenseService::getPersonType($countryId);

                    $addPercent = TourService::getCompanyAddPercent($companyId);
                    $hotelTotal = 0;

                    $roomAmounts = $roomAmounts ?: ExpenseService::getRoomingAmounts($data);
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

    public static function convertExpensePrice(&$data, $attribute): void
    {
        $mainCurrency = ExpenseService::getMainCurrency();
        if ($mainCurrency) {
            $attributeCurrency = $data["{$attribute}_currency"] ?? null;
            if ($attributeCurrency && $attributeCurrency != $mainCurrency->to->value) {
                $data["{$attribute}_converted"] = round(($data[$attribute] ?? 0) / $mainCurrency->rate, 2);
            }
        }
    }

    public static function calculateExpensesPrice($expenses, bool $isUsd = true): float
    {
        $result = 0;
        foreach ($expenses as $expense) {
            $result += ExpenseService::calculateExpensePrice($expense, $isUsd);
        }
        return $result ?: 0;
    }

    public static function calculateExpensePrice($expense, bool $isUsd = true): float
    {
        /** @var TourDayExpense $expense */
        if ($isUsd) {
            $result = $expense->price_result;
        } else {
            $currencyUzs = ExpenseService::getUzsToUsdCurrency();
            $result = round($expense->price_result * $currencyUzs?->rate, 2);
        }

        return $result ?: 0;
    }

    public static function getUzsToUsdCurrency(): ?Currency
    {
        /** @var Currency $currency */
        $currency = CacheService::remember(
            'currency_uzs_usd',
            function() {
                /** @var Currency $currency */
                $currency = Currency::query()
                    ->where('from', CurrencyEnum::UZS->value)
                    ->where('to', CurrencyEnum::USD->value)
                    ->first();
                return $currency;
            }
        );

        return $currency;
    }

    public static function getCurrency(CurrencyEnum $to): ?Currency
    {
        /** @var Currency $currency */
        $currency = CacheService::remember(
            'currency_usd',
            function() use ($to) {
                /** @var Currency $currency */
                $currency = Currency::query()->where('to', $to->value)->first();
                return $currency;
            }
        );

        return $currency;
    }

    public static function getMainCurrency(): ?Currency
    {
        /** @var Currency $currency */
        $currency = CacheService::remember(
            'currency_main',
            function() {
                /** @var Currency $currency */
                $currency = Currency::query()->where('to', CurrencyEnum::USD->value)->first();
//                $currency = Currency::query()->where('is_main', true)->first();
                return $currency;
            }
        );

        return $currency;
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

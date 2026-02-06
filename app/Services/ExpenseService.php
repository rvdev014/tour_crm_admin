<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Show;
use App\Models\Tour;
use App\Models\Hotel;
use App\Models\Train;
use App\Models\Museum;
use App\Models\Country;
use App\Models\Currency;
use App\Enums\TourStatus;
use App\Enums\ExpenseType;
use App\Models\MuseumItem;
use App\Models\Restaurant;
use App\Enums\CurrencyEnum;
use Illuminate\Support\Arr;
use App\Models\HotelPeriod;
use App\Enums\ExpenseStatus;
use App\Models\TourRoomType;
use App\Enums\RoomPersonType;
use App\Enums\RoomSeasonType;
use App\Models\HotelRoomType;
use App\Models\TourDayExpense;
use Illuminate\Support\Collection;

class ExpenseService
{
    public static function getAllExpenses(Tour $tour): Collection
    {
        if ($tour->isCorporate()) {
            return ExpenseService::getAllExpensesCorporate($tour);
        }
        return ExpenseService::getAllExpensesTps($tour);
    }

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
                    roomAmounts: ExpenseService::getRoomingAmountsForExpense($expense),
                    isTps: false
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
                $expenseArr = $expense->toArray();
                $updatedExpense = ExpenseService::mutateExpense(
                    data: $expenseArr,
                    totalPax: $totalPax,
                    countryId: $updatedData['country_id'],
                    roomAmounts: $roomingAmounts,
                    day: $day,
                    isTps: true
                );

                $expensePrice = $updatedExpense['price_converted'] ?? $updatedExpense['price'] ?? 0;
                $expense->update(array_merge($updatedExpense, ['price_result' => $expensePrice]));

                $expensesTotal += $expensePrice;
            }
        }

        return $expensesTotal;
    }

    public static function updateExpensesPricesCorporate(Tour $tour, $updatedData = [], $withRooming = false): float
    {
        $expensesTotal = 0;
        $updatedData = array_merge($tour->getAttributes(), $updatedData);

        $totalPax = $tour->getTotalPax();
        if ($withRooming) {
            $roomingAmounts = ExpenseService::getRoomingAmounts($updatedData);
        } else {
            $roomingAmounts = $tour->roomTypes->mapWithKeys(fn($roomType) => [
                $roomType->room_type_id => $roomType->amount
            ]);
        }
        
        foreach ($tour->groups as $group) {
            foreach ($group->expenses as $expense) {
                $expenseArr = $expense->toArray();
                $updatedExpense = ExpenseService::mutateExpense(
                    data: $expenseArr,
                    totalPax: $totalPax,
                    countryId: $updatedData['country_id'],
                    roomAmounts: $roomingAmounts,
                    isTps: false
                );
                dd($updatedExpense);
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
              $day = null,
              $isTps = false
    ): array {
        ExpenseService::convertExpensePrice($data, 'price');
        $data['status'] = $data['train_status'] ?? $data['status'] ?? ExpenseStatus::New->value;

        switch ($data['type']) {
            case ExpenseType::Hotel->value:
                /** @var Hotel $hotel */
                $hotel = Hotel::query()->find($data['hotel_id']);
                if (!$hotel) {
                    return $data;
                }
                
                $period = ExpenseService::getHotelPeriod($hotel, $day ? $day['date'] : $data['date']);
                if (!$period) {
                    return $data;
                }
                
                $personType = ExpenseService::getPersonType($countryId);
                
                $hotelTotal = 0;
                $roomAmounts = $roomAmounts ?: ExpenseService::getRoomingAmounts($data);
                
                foreach ($roomAmounts as $roomTypeId => $amount) {
                    if (empty($amount)) {
                        continue;
                    }
                    
                    $totalNights = $data['hotel_total_nights'] ?? 1;
                    
                    // For TPS tours: if check-in time is before 14:00, calculate as 1.5 days instead of 1
                    //                        if ($isTps && $totalNights == 1 && !empty($data['hotel_checkin_time'])) {
                    //                            $checkinTime = \Carbon\Carbon::parse($data['hotel_checkin_time']);
                    //                            if ($checkinTime->format('H:i') < '14:00') {
                    //                                $totalNights = 1.5;
                    //                            }
                    //                        }
                    
                    //                        $totalNights += 1;
                    
                    /** @var HotelRoomType $hotelRoomType */
                    $hotelRoomType = $hotel->roomTypes()
                        ->where('room_type_id', $roomTypeId)
                        ->where('hotel_period_id', $period->id)
                        ->first();
                    
                    if (!$hotelRoomType) {
                        continue;
                    }
                    
                    if ($isTps) {
                        $hotelPrice = $hotelRoomType->getPrice($personType);
                    } else {
                        $hotelPrice = $hotelRoomType->getPriceWithPercent($companyId, $personType);
                    }
                    $hotelTotal += $hotelPrice * $amount * $totalNights;
                }
                
                $data['price'] = $hotelTotal;
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

    public static function getHotelPeriod(Hotel $hotel, $date): ?HotelPeriod
    {
        $hotelDate = Carbon::parse($date);
        /** @var HotelPeriod $currentPeriod */
        $currentPeriod = $hotel->periods()
            ->where('start_date', '<=', $hotelDate)
            ->where('end_date', '>=', $hotelDate)
            // Сортируем: сначала те, у которых разница в днях меньше (самые короткие)
            // В Postgres/MySQL можно сортировать по разнице дат, но проще сделать это в PHP,
            // так как периодов обычно мало (1-3 шт).
            ->get()
            ->sortBy(function ($period) {
                // Считаем длину периода в днях
                return Carbon::parse($period->end_date)->diffInDays(Carbon::parse($period->start_date));
            })
            ->first();
        
        return $currentPeriod;
    }

    public static function convertExpensePrice(&$data, $attribute): void
    {
        $mainCurrency = ExpenseService::getMainCurrency();
        if ($mainCurrency) {
            $attributeCurrency = $data["{$attribute}_currency"] ?? null;
            if ($attributeCurrency && $attributeCurrency != $mainCurrency->from->value) {
                $data["{$attribute}_converted"] = round(($data[$attribute] ?? 0) * $mainCurrency->rate, 2);
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

    public static function calculateExpensesPriceView($expenses): string
    {
        $priority = 'USD';

        $fullPriority = true;
        $result = 0;
        $resultSum = 0;
        foreach ($expenses as $expense) {
            if ($expense->price_currency?->value == $priority) {
                $result += $expense->price;
            } else {
                $fullPriority = false;
            }

            $resultSum += ExpenseService::calculateExpensePrice($expense, false);
        }

        if ($fullPriority) {
            return $result > 0 ? TourService::formatMoney($result, currency: '$') : 0;
        }

        return $resultSum > 0 ? TourService::formatMoney($resultSum, currency: 'sum') : 0;
    }

    public static function calculateExpensePrice($expense, bool $isUsd = true): float
    {
        /** @var TourDayExpense $expense */
        if ($isUsd) {
            $currencyUsd = ExpenseService::getUsdToUzsCurrency();
            $result = round($expense->price_result / $currencyUsd?->rate, 2);
        } else {
            $result = $expense->price_result;
        }

        return $result ?: 0;
    }
    
    public static function getPrice($price, bool $isUsd = true): float
    {
        if ($isUsd) {
            $currencyUsd = ExpenseService::getUsdToUzsCurrency();
            return round($price / $currencyUsd?->rate, 2);
        }
        
        return $price;
    }

    public static function getUsdToUzsCurrency(): ?Currency
    {
        /** @var Currency $currency */
        $currency = CacheService::remember(
            'currency_usd_uzs',
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
                $currency = Currency::query()->where('from', CurrencyEnum::UZS->value)->first();
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

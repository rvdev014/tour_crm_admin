<?php

namespace App\Services;

use App\Enums\CompanyType;
use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Mail\HotelMail;
use App\Mail\RestaurantMail;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\Museum;
use App\Models\MuseumItem;
use App\Models\Restaurant;
use App\Models\RoomType;
use App\Models\Show;
use App\Models\Tour;
use App\Models\TourDayExpense;
use App\Models\TourHotel;
use App\Models\Transport;
use App\Models\User;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Number;

class TourService
{
    public static function getHotelRoomTypes($hotelId): array|Collection
    {
        if (!empty($hotelId)) {
            $result = [];
            $hRoomTypes = HotelRoomType::where('hotel_id', $hotelId)->get();
            foreach ($hRoomTypes as $hRoomType) {
                $result[$hRoomType->id] = "{$hRoomType->roomType->name} {$hRoomType->price}";
            }
            return $result;
        }
        return [];
    }

    public static function getCities($countryId = null, bool $isPluck = true, $isAll = false): array|Collection
    {
        if (!$countryId) {
            $countryId = CacheService::remember(
                'uzbekistan_country_id',
                fn() => Country::query()->where('name', 'Uzbekistan')->first()?->id
            );
            if (!$countryId) {
                throw new \Exception('Country \'Uzbekistan\' not found');
            }
        }

        if (!empty($countryId)) {
            $result = CacheService::remember(
                "cities_{$countryId}",
                fn() => City::query()
                    ->select('name', 'id')
                    ->where('country_id', $countryId)
                    ->get()
            );
            return $isPluck ? $result->pluck('name', 'id')->toArray() : $result->toArray();
        }
        if ($isAll) {
            $result = CacheService::remember('cities', fn() => City::query()->select('name', 'id')->get());
            return $isPluck ? $result->pluck('name', 'id')->toArray() : $result->toArray();
        }
        return [];
    }

    public static function getRestaurants($localCityId): array|Collection
    {
        return CacheService::remember(
            "restaurants_{$localCityId}",
            fn() => Restaurant::query()
                ->select('name', 'id')
                ->where('city_id', $localCityId)
                ->get()
                ->pluck('name', 'id')
                ->toArray()
        );
    }

    public static function getHotels($localCityId): array|Collection
    {
        return CacheService::remember(
            "hotels_{$localCityId}",
            fn() => Hotel::query()
                ->select('name', 'id')
                ->where('city_id', $localCityId)
                ->get()
                ->pluck('name', 'id')
                ->toArray()
        );
    }

    public static function getMuseums($localCityId): array|Collection
    {
        return CacheService::remember(
            "museums_{$localCityId}",
            fn() => Museum::query()
                ->select('name', 'id')
                ->where('city_id', $localCityId)
                ->get()
                ->pluck('name', 'id')
                ->toArray()
        );
    }

    public static function getMuseumsByIds($ids): array|Collection
    {
        return CacheService::remember(
            "museums_ids_" . implode(',', $ids),
            fn() => Museum::query()
                ->select('name', 'id')
                ->whereIn('id', $ids)
                ->get()
                ->pluck('name', 'id')
                ->toArray()
        );
    }

    public static function getMuseumItems($museumIds): array|Collection
    {
        return CacheService::remember(
            "museum_items_" . implode(',', $museumIds),
            fn() => MuseumItem::query()
                ->select('name', 'id')
                ->whereIn('museum_id', $museumIds)
                ->get()
                ->pluck('name', 'id')
                ->toArray()
        );
    }

    public static function getShows($localCityId): array|Collection
    {
        return CacheService::remember(
            "shows_{$localCityId}",
            fn() => Show::query()
                ->select('name', 'id')
                ->where('city_id', $localCityId)
                ->get()
                ->pluck('name', 'id')
                ->toArray()
        );
    }

    public static function isVisible(Tour $tour): bool
    {
        /** @var User $user */
        $user = auth()->user();
        if ($user->isAdmin()) {
            return true;
        }
        if ($tour->created_by == $user->id) {
            return true;
        }
        return false;
    }

    public static function getTotalCount(TourType $tourType, $startDate, $endDate, $countryId): int
    {
        return Tour::query()
            ->where('type', $tourType)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($countryId, fn($query, $countryId) => $query->where('country_id', $countryId))
            ->count();
    }

    public static function getTpsTotalIncome($startDate, $endDate, $countryId): float|int
    {
        $totalExpense = TourDayExpense::query()
            ->whereHas(
                'tourDay',
                fn($query) => $query->whereHas('tour', function ($q) use ($countryId, $startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                        ->where('type', TourType::TPS)
                        ->when($countryId, fn($q, $countryId) => $q->where('country_id', $countryId));
                })
            )->sum('price');

        $totalPrice = TourService::getTotalPrice(TourType::TPS, $startDate, $endDate, $countryId);

        return $totalPrice - $totalExpense;
    }

    public static function getCorporateTotalIncome($startDate, $endDate, $countryId): float|int
    {
        $totalExpense = TourHotel::query()
            ->whereHas('tour', function ($q) use ($countryId, $startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                    ->where('type', TourType::Corporate)
                    ->when($countryId, fn($q, $countryId) => $q->where('country_id', $countryId));
            })->sum('price');

        $totalPrice = TourService::getTotalPrice(TourType::Corporate, $startDate, $endDate, $countryId);

        return $totalPrice - $totalExpense;
    }

    public static function getTotalPrice(TourType $tourType, $startDate, $endDate, $countryId): float|int
    {
        return Tour::query()
            ->where('type', $tourType)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($countryId, fn($query, $countryId) => $query->where('country_id', $countryId))
            ->sum('price');
    }

    public static function tourNextId(): int
    {
        $toursTableSequence = DB::selectOne('SELECT last_value + 1 AS next_id FROM tours_id_seq;');
        return ($toursTableSequence?->next_id ?? 1);
    }

    public static function getGroupNumber(TourType $tourType): string
    {
        $userName = auth()->user()->name;
        $firstLetter = substr($userName, 0, 1);
        //        $corporateToursCount = Tour::where('type', $tourType)->count() + 1;

        if ($tourType == TourType::TPS) {
            //            $number = self::threeDigit(self::tourNextId());
            $lastLetter = 'T';
        } else {
            $lastLetter = 'C';
        }

        $number = self::addHundred(self::tourNextId());

        $currentYear = date('y');
        return "{$firstLetter}{$number}{$currentYear}{$lastLetter}";
    }

    public static function threeDigit(int $number): string
    {
        return str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    public static function addHundred(int $number): string
    {
        return $number + 100;
    }

    public static function getAdditionalPercent($companyId): float|int
    {
        if (!$companyId) {
            return 0;
        }
        $company = Company::find($companyId);
        return $company?->additional_percent ?? 0;
    }

    public static function getCompanyAddPercent($companyId): ?int
    {
        $addPercent = null;
        if ($companyId) {
            /** @var Company $company */
            $company = Company::query()->select('additional_percent')->find($companyId);
            $addPercent = $company?->additional_percent ?? null;
        }
        return $addPercent;
    }

    public static function getHotelPrice($hotelRoomTypeId, $additionalPercent): float|int
    {
        if (!$hotelRoomTypeId) {
            return 0;
        }

        $hotelRoomType = HotelRoomType::find($hotelRoomTypeId);
        if ($hotelRoomType) {
            if ($additionalPercent) {
                return $hotelRoomType->price + ($hotelRoomType->price * $additionalPercent / 100);
            }
            return $hotelRoomType->price;
        }
        return 0;
    }

    public static function getMuseumPrice($museumId, $pax, $museumItemId = null): float|int
    {
        if (!$museumId || !$pax) {
            return 0;
        }
        if ($museumItemId) {
            $museumItem = MuseumItem::find($museumItemId);
            return $museumItem->price_per_person * $pax;
        }
        $museum = Museum::find($museumId);
        return $museum->price_per_person * $pax;
    }

    public static function getTransportPrice($transportType, $comfortLevel): float|int
    {
        if (!$transportType || !$comfortLevel) {
            return 0;
        }
        $transport = Transport::where('type', $transportType)->where('comfort_level', $comfortLevel)->first();
        return $transport?->price ?? 0;
    }

    public static function formatMoney($money, $divideBy = 0, $currency = null): ?string
    {
        if (blank($money)) {
            return null;
        }
        if (!is_numeric($money)) {
            return $money;
        }
        if ($divideBy) {
            $money /= $divideBy;
        }
        return Number::currency($money, Table::$defaultCurrency);
    }

    public static function generateRoomingSchema(): array
    {
        return [
            Grid::make(3)->schema(
                RoomType::all()->map(function (RoomType $roomType) {
                    return TextInput::make("room_type_{$roomType->id}")
                        ->label($roomType->name)
                        ->formatStateUsing(function ($record) use ($roomType) {
                            if (!$record) {
                                return 0;
                            }
                            $tourRoomType = $record->roomTypes->first(
                                fn($item) => $item->room_type_id == $roomType->id
                            );
                            return $tourRoomType?->amount ?? 0;
                        })
                        ->numeric();
                })->toArray()
            )
        ];
    }

    public static function getCompanies(CompanyType $type)
    {
        return Company::query()->select('name', 'id')->where('type', $type)->get()->pluck('name', 'id')->toArray();
    }

    public static function sendMails($tourData, $days): void
    {
        if (app()->environment('local')) {
            return;
        }

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
            if (!empty($hotel->email)) {
                Mail::to($hotel->email)->send(new HotelMail($date, $hotelItem['expense'], $tourData));
            }
        }

        foreach ($restaurantsData as $date => $restaurantItem) {
            /** @var Restaurant $restaurant */
            $restaurant = $restaurantItem['restaurant'];
            if (!empty($restaurant->email)) {
                Mail::to($restaurant->email)->send(new RestaurantMail($date, $restaurantItem['expense'], $tourData));
            }
        }
    }
}

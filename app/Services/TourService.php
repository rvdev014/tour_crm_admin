<?php

namespace App\Services;

use App\Enums\CompanyType;
use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Models\City;
use App\Models\Company;
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
use Illuminate\Support\Number;

class TourService
{
    public static function onPax($get, $set)
    {
        $type = $get('type');
        if ($type == ExpenseType::Lunch->value || $type == ExpenseType::Dinner->value || $type == ExpenseType::Show->value) {
            $price = !empty($get('price')) ? $get('price') : 0;
            $pax = !empty($get('pax')) ? $get('pax') : 0;
            if (!$pax) {
                $set('total_price', $price);
            } else {
                $set('total_price', $price * $pax);
            }
        }

        if ($type == ExpenseType::Museum->value) {
            $price = TourService::getMuseumPrice(
                $get('museum_id'),
                $get('pax'),
                $get('museum_item_id')
            );
            $set('price', $price);
        }
    }

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

    public static function getCities($countryId, bool $isPluck = true, $isAll = false): array|Collection
    {
        if (!empty($countryId)) {
            $result = City::where('country_id', $countryId)->get();
            return $isPluck ? $result->pluck('name', 'id') : $result;
        }
        if ($isAll) {
            $result = City::all();
            return $isPluck ? $result->pluck('name', 'id') : $result;
        }
        return [];
    }

    public static function getRestaurants($localCityId, $globalCityId, $countryId): array|Collection
    {
        $result = [];
        if (!empty($localCityId)) {
            $result = Restaurant::where('city_id', $localCityId)->get()->pluck('name', 'id');
        }
        if (!empty($globalCityId)) {
            $result = Restaurant::where('city_id', $globalCityId)->get()->pluck('name', 'id');
        }
        if (!empty($countryId)) {
            $result = Restaurant::where('country_id', $countryId)->get()->pluck('name', 'id');
        }
        return $result;
    }

    public static function getHotels($localCityId, $globalCityId, $countryId): array|Collection
    {
        $result = [];
        if (!empty($localCityId)) {
            $result = Hotel::where('city_id', $localCityId)->get()->pluck('name', 'id');
        }
        if (!empty($globalCityId)) {
            $result = Hotel::where('city_id', $globalCityId)->get()->pluck('name', 'id');
        }
        if (!empty($countryId)) {
            $result = Hotel::where('country_id', $countryId)->get()->pluck('name', 'id');
        }
        return $result;
    }

    public static function getMuseums($localCityId, $globalCityId, $countryId): array|Collection
    {
        $result = [];
        if (!empty($localCityId)) {
            $result = Museum::where('city_id', $localCityId)->get()->pluck('name', 'id');
        }
        if (!empty($globalCityId)) {
            $result = Museum::where('city_id', $globalCityId)->get()->pluck('name', 'id');
        }
        if (!empty($countryId)) {
            $result = Museum::where('country_id', $countryId)->get()->pluck('name', 'id');
        }
        return $result;
    }

    public static function getShows($localCityId, $globalCityId, $countryId): array|Collection
    {
        $result = [];
        if (!empty($localCityId)) {
            $result = Show::where('city_id', $localCityId)->get()->pluck('name', 'id');
        }
        if (!empty($globalCityId)) {
            $result = Show::where('city_id', $globalCityId)->get()->pluck('name', 'id');
        }
        if (!empty($countryId)) {
            $result = Show::where('country_id', $countryId)->get()->pluck('name', 'id');
        }
        return $result;
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
            $number = self::threeDigit(self::tourNextId());
            $lastLetter = 'T';
        } else {
            $number = self::addHundred(self::tourNextId());
            $lastLetter = 'C';
        }

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
                    return TextInput::make("hotel_type_{$roomType->id}")
                        ->label($roomType->name)
                        ->formatStateUsing(function ($record) use ($roomType) {
                            if (!$record) {
                                return 0;
                            }
                            $roomType = $record->hotelRoomTypes->first(
                                fn($hotelRoomType) => $hotelRoomType->hotel_room_type_id == $roomType->id
                            );
                            return $roomType?->amount ?? 0;
                        })
                        ->numeric();
                })->toArray()
            )
        ];
    }

    public static function getCompanies(CompanyType $type)
    {
        return Company::where('type', $type)->get()->pluck('name', 'id');
    }
}

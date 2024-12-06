<?php

namespace App\Services;

use App\Enums\TourType;
use App\Models\Company;
use App\Models\HotelRoomType;
use App\Models\Museum;
use App\Models\MuseumItem;
use App\Models\Tour;
use App\Models\TourDayExpense;
use App\Models\TourHotel;
use App\Models\Transport;
use App\Models\User;

class TourService
{
    public static function isIncomeVisible(Tour $tour): bool
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

    public static function getGroupNumber(TourType $tourType): string
    {
        $userName = auth()->user()->name;
        $firstLetter = substr($userName, 0, 1);
        $corporateToursCount = Tour::where('type', $tourType)->count() + 1;

        if ($tourType == TourType::TPS) {
            $number = self::threeDigit($corporateToursCount);
            $lastLetter = 'T';
        } else {
            $number = self::addHundred($corporateToursCount);
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
}

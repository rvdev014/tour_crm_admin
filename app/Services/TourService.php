<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\City;
use App\Models\Show;
use App\Models\Tour;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Train;
use App\Models\Driver;
use App\Models\Museum;
use App\Enums\TourType;
use App\Models\Company;
use App\Models\Country;
use App\Models\RoomType;
use App\Models\TourHotel;
use App\Enums\CompanyType;
use App\Enums\ExpenseType;
use App\Models\MuseumItem;
use App\Models\Restaurant;
use Filament\Tables\Table;
use App\Enums\ExpenseStatus;
use App\Enums\TransportType;
use App\Enums\RoomPersonType;
use App\Models\TourDayExpense;
use Illuminate\Support\Number;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;

class TourService
{
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
            return $isPluck ? $result->pluck('name', 'id') : $result;
        }
        if ($isAll) {
            $result = CacheService::remember('cities', fn() => City::query()->select('name', 'id')->get());
            return $isPluck ? $result->pluck('name', 'id') : $result;
        }
        return [];
    }

    public static function getDrivers(): array|Collection
    {
        return CacheService::remember(
            'drivers',
            fn() => Driver::query()
                ->select('name', 'id')
                ->get()
                ->pluck('name', 'id')
        );
    }

    public static function getTrains(): array|Collection
    {
        return CacheService::remember(
            "trains",
            fn() => Train::query()
                ->select('name', 'id')
                ->get()
                ->pluck('name', 'id')
        );
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
                fn($query) => $query->whereHas('tour', function($q) use ($countryId, $startDate, $endDate) {
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
            ->whereHas('tour', function($q) use ($countryId, $startDate, $endDate) {
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
        if (DB::table('tours')->count() == 0) {
            return 1;
        }

        $toursTableSequence = DB::selectOne("SELECT last_value + 1 AS next_id FROM tours_id_seq;");
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

    public static function addHundred(int $number): string
    {
        return $number + 100;
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

    public static function generateRoomingSchema($firstThree = false): array
    {
        if ($firstThree) {
            $roomTypes = RoomType::query()->limit(3)->get();
        } else {
            $roomTypes = RoomType::query()->skip(3)->get();
        }

        $result = $roomTypes->map(function(RoomType $roomType) {
            return TextInput::make("room_type_$roomType->id")
                ->label($roomType->name)
                ->formatStateUsing(function($record) use ($roomType) {
                    if (!$record) {
                        return 0;
                    }
                    $tourRoomType = $record->roomTypes->first(fn($item) => $item->room_type_id == $roomType->id);
                    return $tourRoomType?->amount ?? 0;
                })
                ->numeric();
        });

        return [
            Grid::make(3)->schema($result->toArray())
        ];
    }

    public static function getCompanies(CompanyType $type)
    {
        return Company::query()->select('name', 'id')->where('type', $type)->get()->pluck('name', 'id');
    }

    public static function sendTelegram($tourData, $isCorporate = false): void
    {
        $transportsData = [];

        if ($isCorporate) {
            $expenses = $tourData['expenses'] ?? [];
            foreach ($expenses as $expense) {
                if ($expense['type'] != ExpenseType::Transport->value || $expense['status'] != ExpenseStatus::Confirmed->value) {
                    continue;
                }

                $driverIds = $expense['transport_driver_ids'] ?? [];
                if (empty($driverIds)) {
                    continue;
                }

                foreach ($driverIds as $driverId) {
                    $totalPax = count($tourData['passengers'] ?? []);
                    $transportsData[$driverId][] = [
                        'driver_id' => $driverId,
                        'pax' => $totalPax,
                        'driver_ids' => $driverIds,
                        'to_city' => $expense['to_city_id'],
                        'transport_place' => $expense['transport_place'],
                        'route' => $expense['transport_route'],
                        'date' => $expense['date'],
                        'transport_type' => $tourData['transport_type'],
                        'price' => $expense['price'],
                        'comment' => $expense['comment'],
                    ];
                }
            }
        } else {
            $days = $tourData['days'] ?? [];
            foreach ($days as $day) {
                $expenses = $day['expenses'] ?? [];
                foreach ($expenses as $expense) {
                    if ($expense['type'] != ExpenseType::Transport->value || $expense['status'] != ExpenseStatus::Confirmed->value) {
                        continue;
                    }

                    $driverIds = $expense['transport_driver_ids'] ?? [];
                    if (empty($driverIds)) {
                        continue;
                    }

                    $date = Carbon::parse($day['date'])->format('Y-m-d');
                    $time = Carbon::parse($expense['transport_time'])->format('H:i');
                    $totalPax = ($tourData['pax'] ?? 0) + ($tourData['leader_pax'] ?? 0);

                    foreach ($driverIds as $driverId) {
                        $transportsData[$driverId][] = [
                            'driver_id' => $driverId,
                            'pax' => $totalPax,
                            'driver_ids' => $driverIds,
                            'to_city' => $expense['to_city_id'],
                            'transport_place' => $expense['transport_place'],
                            'route' => $expense['transport_route'],
                            'date' => "{$date} {$time}",
                            'transport_type' => $tourData['transport_type'],
                            'price' => $expense['price'],
                            'comment' => $expense['comment'],
                        ];
                    }
                }
            }
        }

        if (empty($transportsData)) {
            return;
        }

        foreach ($transportsData as $driverId => $transportItems) {
            $driver = Driver::find($driverId);
            if (empty($driver?->chat_id)) {
                continue;
            }

            $title = "Tour {$tourData['group_number']}\n";
            $message = '';
            foreach ($transportItems as $transportItem) {
                $message .= TourService::getOneMessage($transportItem, false);
            }

            $message = <<<HTML
$title$message

Office phone: +998333377752
HTML;

            TelegramService::sendMessage($driver->chat_id, $message, ['parse_mode' => 'HTML']);
        }
    }

    public static function sendTelegramTransfer($data): void
    {
        $driverIds = $data['driver_ids'] ?? [];
        foreach ($driverIds as $driverId) {
            /** @var Driver $driver */
            $driver = Driver::query()->find($driverId ?? null);
            if (empty($driver?->chat_id)) {
                continue;
            }

            $message = TourService::getOneMessage([
                'driver_id' => $driverId,
                'pax' => $data['pax'],
                'driver_ids' => $driverIds,
                'to_city' => $data['to_city_id'],
                'transport_place' => $data['place_of_submission'],
                'route' => $data['route'],
                'mark' => $data['mark'],
                'nameplate' => $data['nameplate'],
                'date' => $data['date_time'],
                'transport_type' => $data['transport_type'],
                'price' => $data['price'] ?? '',
                'comment' => $data['comment'],
            ]);

            TelegramService::sendMessage($driver->chat_id, $message, ['parse_mode' => 'HTML']);
        }
    }

    public static function getOneMessage($data, bool $withPhone = true): string
    {
        $drivers = Driver::query()
            ->whereIn('id', $data['driver_ids'] ?? [])
            ->get()
            ->map(fn(Driver $driver) => $driver->name)
            ->implode(', ');

        $pax = $data['pax'] ?? 0;
        $route = $data['route'] ?? '-';
        $mark = $data['mark'] ?? '-';
        $nameplate = $data['nameplate'] ?? '-';
        $toCity = $data['to_city'] ? City::find($data['to_city']) : null;
        $place = $data['transport_place'] ?? '-';
        $comment = $data['comment'] ?? '-';
        $date = $data['date'] ? Carbon::parse($data['date'])->format('d-M H:i') : '-';
        $transportType = $data['transport_type'] ? self::getEnum(TransportType::class, $data['transport_type']) : '-';
        $price = $data['price'] ?? '';

        $result = <<<HTML

<b>Drivers:</b> {$drivers}
<b>Pax:</b> {$pax}
<b>Date and time:</b> {$date}
<b>City:</b> {$toCity?->name}
<b>Pickup location:</b> {$place}
<b>Transport:</b> {$transportType}
<b>Route:</b> {$route}
<b>Marka:</b> {$mark}
<b>Табличка:</b> {$nameplate}
<b>Comment:</b> {$comment}
HTML;

        if ($withPhone) {
            $result .= <<<HTML


Office phone: +998333377752
HTML;
        }

        return $result;
    }

    public static function getEnum($enumClass, $value): string
    {
        if ($value instanceof $enumClass) {
            return $value->getLabel();
        }

        return $enumClass::from($value)->getLabel();
    }

    public static function calculateHotelNights(?string $date, ?string $checkIn, ?string $checkOutDateTime): float
    {
        if (!$date || !$checkIn || !$checkOutDateTime) {
            return 0;
        }

        $date = \Illuminate\Support\Carbon::parse($date);
        $hotelCheckinDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $checkIn);
        $hotelCheckoutDateTime = Carbon::parse($checkOutDateTime);

        $diffInDays = $hotelCheckinDateTime->clone()->startOfDay()->diffInDays(
            $hotelCheckoutDateTime->clone()->startOfDay()
        );

        if ($hotelCheckinDateTime->format('H:i') < '14:00') {
            $diffInDays += 0.5;
        }
        if ($hotelCheckoutDateTime->format('H:i') > '12:00') {
            $diffInDays += 0.5;
        }

        return $diffInDays;
    }
}

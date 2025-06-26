<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Tour;
use App\Models\Hotel;
use App\Models\TourDay;
use App\Enums\ExpenseType;
use App\Models\TourRoomType;
use App\Enums\RoomPersonType;
use App\Models\HotelRoomType;
use App\Models\TourDayExpense;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;

class ExportHotelService
{
    public static function getHotelsData(Tour $tour): Collection
    {
        if ($tour->isCorporate()) {
            return self::getHotelsDataCorporate($tour);
        }

        return self::getHotelsDataTps($tour);
    }

    protected static function getHotelPrices(
        TourDayExpense $hotelExpense,
                       $date,
                       $roomAmounts,
                       $addPercent,
                       $personType
    ): Collection {
        $hotelPrices = collect();
        $seasonType = ExpenseService::getSeasonType($hotelExpense->hotel, $date);

        foreach ($roomAmounts as $roomTypeId => $amount) {
            /** @var HotelRoomType $hotelRoomType */
            $hotelRoomType = $hotelExpense->hotel->roomTypes()
                ->where('room_type_id', $roomTypeId)
                ->where('season_type', $seasonType)
                ->first();

            if ($hotelRoomType) {
                $price = $hotelRoomType->getPrice($addPercent, $personType);
                $hotelPrices->put($hotelRoomType->roomType->name, $price);
            }
        }

        return $hotelPrices;
    }

    public static function saveReport($hotelItem, $tempDir): ?string
    {
        $hotelName = str_replace([' ', '(', ')'], '_', $hotelItem['hotelName']);
        $fileName = $tempDir . '/Hotel_' . $hotelName . '.docx';

        $templateProcessor = ExportHotelService::getReplacedTemplateFirst($hotelItem);
        $templateProcessor->saveAs($fileName);

        return $fileName;
    }

    public static function getHotelsDataTps(Tour $tour): Collection
    {
        $result = collect();

        $tourPax = $tour->getTotalPax();
        $groupNum = $tour->group_number;
        $countryName = $tour->country->name;
        $personType = ExpenseService::getPersonType($tour->country->id);
        $addPercent = 0;

        $roomAmounts = $tour->roomTypes->mapWithKeys(fn(TourRoomType $rt) => [$rt->roomType->name => $rt->amount]);
        $roomAmountsById = $tour->roomTypes->mapWithKeys(fn(TourRoomType $rt) => [$rt->roomType->id => $rt->amount]);

        $prevHotelExpense = null;

        /** @var Collection<TourDay> $tourDays */
        $tourDays = $tour->days()->orderBy('date')->get();
        foreach ($tourDays as $tourDay) {
            /** @var TourDayExpense $hotelExpense */
            $hotelExpense = $tourDay->expenses->first(fn(TourDayExpense $exp) => $exp->type === ExpenseType::Hotel);
            if (!$hotelExpense) {
                continue;
            }

            $date = $tourDay->date;
            $city = $tourDay->city->name;
            $hotelName = $hotelExpense->hotel->name;
            $hotelPrices = self::getHotelPrices(
                hotelExpense: $hotelExpense,
                date: $date,
                roomAmounts: $roomAmountsById,
                addPercent: $addPercent,
                personType: $personType
            );

            if ($hotelExpense->hotel_checkin_time) {
                $date->setTimeFromTimeString($hotelExpense->hotel_checkin_time);
            }

            $hotelItem = [
                'tour_number' => $tour->group_number,
                'payment_method' => $tour->payment_type->getLabel(),
                'hotel' => $hotelExpense->hotel,
                'hotelId' => $hotelExpense->hotel_id,
                'hotelName' => $hotelName,
                'hotelPrices' => $hotelPrices,
                'hotelTotalPrice' => $hotelPrices->sum(),
                'guests' => $tour->company->name,
                'total_nights' => $hotelExpense->hotel_total_nights,
                'date' => $date->format('d.m.Y H:i'),
                'city' => $city,
                'country' => $countryName,
                'pax' => $tourPax,
                'groupNum' => $groupNum,
                'rooming' => $roomAmounts,
                'arrivals' => [
                    $date->format('d.m.Y H:i')
                ],
                'departures' => [
                    $date->clone()->setTimeFromTimeString($hotelExpense->hotel_checkout_time)->format('d.m.Y H:i')
                ],
                'operator' => $tour->createdBy->name ?? '-',
            ];

            //            $existingHotel = $result->get($hotelExpense->hotel_id);
            //            if ($existingHotel) {
            //                // If the hotel is the same as the previous day, it's not an arrival or departure
            //                if ($prevHotelExpense && $prevHotelExpense['hotelId'] === $hotelExpense->hotel_id) {
            //                    continue;
            //                }
            //
            //                // If the hotel is different from the previous day, it's a new arrival
            //                $existingHotel['arrivals'][] = $date->format('d.m.Y H:i');
            //                $result->put($hotelExpense->hotel_id, $existingHotel);
            //
            //                // And departure for previous hotel
            //                if ($prevHotelExpense) {
            //                    $prevHotel = $result->get($prevHotelExpense['hotelId']);
            //                    $prevHotel['departures'][] = $date->clone()->setTimeFromTimeString($hotelExpense->hotel_checkout_time)->format('d.m.Y  H:i');
            //                    $result->put($prevHotelExpense['hotelId'], $prevHotel);
            //                }
            //            } else {
            //                // If the hotel is different from the previous day, it's a new hotel
            //                // Add departures to the previous hotel
            //                if ($prevHotelExpense) {
            //                    $prevHotel = $result->get($prevHotelExpense['hotelId']);
            ////                    $prevHotel['departures'][] = $date->clone()->setTimeFromTimeString($hotelExpense->hotel_checkout_time)->format('d.m.Y H:i');
            //                    $result->put($prevHotelExpense['hotelId'], $prevHotel);
            //                }
            //
            //                // First day of hotel
            //                $hotelItem['arrivals'][] = $date->format('d.m.Y H:i');
            //                $prevHotel['departures'][] = $date->clone()->setTimeFromTimeString($hotelExpense->hotel_checkout_time)->format('d.m.Y H:i');
            //                $result->put($hotelExpense->hotel_id, $hotelItem);
            //            }

            $result->put($hotelExpense->hotel_id, $hotelItem);
            $prevHotelExpense = $hotelItem;
        }

        //        $lastHotelItem = $result->last();
        //        if ($lastHotelItem) {
        //            $lastHotelItem['departures'][] = $tour->end_date->format('d.m.Y H:i');
        //            $result->put($lastHotelItem['hotelId'], $lastHotelItem);
        //        }

        return $result;
    }

    public static function getHotelsDataCorporate(Tour $tour): Collection
    {
        $result = collect();

        $tourPax = $tour->getTotalPax();
        $groupNum = $tour->group_number;
        $countryName = '-';
        $personType = RoomPersonType::Uzbek;
        $addPercent = TourService::getCompanyAddPercent($tour->company_id);

        $roomAmounts = $tour->roomTypes->mapWithKeys(fn(TourRoomType $rt) => [$rt->roomType->name => $rt->amount]);
        $roomAmountsById = $tour->roomTypes->mapWithKeys(fn(TourRoomType $rt) => [$rt->roomType->id => $rt->amount]);

        $prevHotelExpense = null;

        $dates = $tour->expenses->pluck('date')->unique();

        foreach ($dates as $date) {
            /** @var TourDayExpense $hotelExpense */
            $hotelExpense = $tour->getExpenseByDate($date, ExpenseType::Hotel);
            if (!$hotelExpense) {
                continue;
            }

            $city = $hotelExpense->city?->name;
            $hotelName = $hotelExpense->hotel?->name;
            $hotelPrices = self::getHotelPrices(
                hotelExpense: $hotelExpense,
                date: $date,
                roomAmounts: $roomAmountsById,
                addPercent: $addPercent,
                personType: $personType
            );

            if ($hotelExpense->hotel_checkin_time) {
                $date->setTimeFromTimeString($hotelExpense->hotel_checkin_time);
            }

            $hotelItem = [
                'tour_number' => $tour->group_number,
                'payment_method' => $tour->payment_type->getLabel(),
                'hotel' => $hotelExpense->hotel,
                'hotelId' => $hotelExpense->hotel_id,
                'hotelName' => $hotelName,
                'hotelPrices' => $hotelPrices,
                'total_nights' => $hotelExpense->hotel_total_nights,
                'hotelTotalPrice' => $hotelPrices->sum(),
                'guests' => $hotelExpense->tourGroup->passengers->pluck('name')->implode(', '),
                'date' => $date->format('d.m.Y H:i'),
                'city' => $city,
                'country' => $countryName,
                'pax' => $tourPax,
                'groupNum' => $groupNum,
                'rooming' => $roomAmounts,
                'arrivals' => [
                    $date->format('d.m.Y H:i')
                ],
                'departures' => [
                    $date->clone()->setTimeFromTimeString($hotelExpense->hotel_checkout_time)->format('d.m.Y H:i')
                ],
            ];

//            $existingHotel = $result->get($hotelExpense->hotel_id);
//            if ($existingHotel) {
//                // If the hotel is the same as the previous day, it's not an arrival or departure
//                if ($prevHotelExpense && $prevHotelExpense['hotelId'] === $hotelExpense->hotel_id) {
//                    continue;
//                }
//
//                // If the hotel is different from the previous day, but exists in the result, it's a new arrival
//                $existingHotel['arrivals'][] = $date->format('d.m.Y H:i');
//                $result->put($hotelExpense->hotel_id, $existingHotel);
//
//                // And departure for previous hotel
//                if ($prevHotelExpense) {
//                    $prevHotel = $result->get($prevHotelExpense['hotelId']);
//                    $prevHotel['departures'][] = $date->clone()->setTimeFromTimeString(
//                        $hotelExpense->hotel_checkout_time
//                    )->format('d.m.Y H:i');
//                    $result->put($prevHotelExpense['hotelId'], $prevHotel);
//                }
//            } else {
//                // If the hotel is different from the previous day, it's a new hotel
//                // Add departures to the previous hotel
//                if ($prevHotelExpense) {
//                    $prevHotel = $result->get($prevHotelExpense['hotelId']);
//                    //                    $prevHotel['departures'][] = $date->clone()->setTimeFromTimeString($hotelExpense->hotel_checkout_time)->format('d.m.Y H:i');
//                    $result->put($prevHotelExpense['hotelId'], $prevHotel);
//                }
//
//                // First day of hotel
//                $hotelItem['arrivals'][] = $date->format('d.m.Y H:i');
//                $prevHotel['departures'][] = $date->clone()->setTimeFromTimeString(
//                    $hotelExpense->hotel_checkout_time
//                )->format('d.m.Y H:i');
//                $result->put($hotelExpense->hotel_id, $hotelItem);
//            }

            $prevHotelExpense = $hotelItem;
        }

        //        $lastHotelItem = $result->last();
        //        if ($lastHotelItem) {
        //            $lastHotelItem['departures'][] = $tour->end_date->format('d.m.Y H:i');
        //            $result->put($lastHotelItem['hotelId'], $lastHotelItem);
        //        }

        return $result;
    }

    public static function getTemplateFirstPath(): string
    {
        return __DIR__ . '/Templates/Report_hotel.docx';
    }

    public static function getTemplateSecondPath(): string
    {
        return __DIR__ . '/Templates/Client_voucher.docx';
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public static function getReplacedTemplateFirst($hotelItem): TemplateProcessor
    {
        $templateProcessor = new TemplateProcessor(self::getTemplateFirstPath());

        $rooming = collect($hotelItem['rooming'] ?? []);
        $arrivals = collect($hotelItem['arrivals'] ?? []);
        $departures = collect($hotelItem['departures'] ?? []);

        $arrivalsStr = $arrivals->map(function($arrival, $index) {
            $label = ++$index . ExportHotelService::getNumberSuffix($index) . " arrival\n";
            return $label . Carbon::parse($arrival)->format('d.m.Y');
        })->implode("\n\n");
        $arrivalTimesStr = $arrivals->map(fn($arrival) => Carbon::parse($arrival)->format('H:i'))->implode("\n\n");

        $departuresStr = $departures->map(function($departure, $index) {
            $label = ++$index . ExportHotelService::getNumberSuffix($index) . " check-out\n";
            return $label . Carbon::parse($departure)->format('d.m.Y');
        })->implode("\n\n");
        $departureTimesStr = $departures->map(fn($departure) => Carbon::parse($departure)->format('H:i'))->implode(
            "\n\n"
        );

        $placeholders = [
            'date' => Carbon::parse($hotelItem['date'])->format('d-M'),
            'country' => $hotelItem['country'],
            'city' => $hotelItem['city'],
            'pax' => $hotelItem['pax'] . ' pax',
            'groupNum' => $hotelItem['groupNum'],
            'hotel' => $hotelItem['hotelName'],
            'rooming' => $rooming->map(fn($amount, $roomType) => "$roomType: $amount")->implode("\t\t\t"),
            'arrivals' => $arrivalsStr,
            'arrivalTimes' => $arrivalTimesStr,
            'outs' => $departuresStr,
            'outsTime' => $departureTimesStr,
            'operator' => $hotelItem['operator'],
        ];
        foreach ($placeholders as $placeholder => $value) {
            $templateProcessor->setValue($placeholder, $value);
        }

        return $templateProcessor;
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public static function getReplacedTemplateSecond($hotelItem): TemplateProcessor
    {
        $templateProcessor = new TemplateProcessor(self::getTemplateSecondPath());

        /** @var Hotel $hotel */
        $hotel = $hotelItem['hotel'];
        $arrivals = collect($hotelItem['arrivals'] ?? []);
        $departures = collect($hotelItem['departures'] ?? []);
        $prices = collect($hotelItem['hotelPrices'] ?? []);
        $roomAmounts = collect($hotelItem['rooming'] ?? []);

        $arrivalsStr = $arrivals->map(fn($arrival) => Carbon::parse($arrival)->format('d.m.Y H:i'))->implode(", ");
        $departuresStr = $departures->map(fn($departure) => Carbon::parse($departure)->format('d.m.Y H:i'))->implode(
            ", "
        );

        $firstArrivalTime = Carbon::parse($arrivals->first())->format('H:i');
        $lastDepartureTime = Carbon::parse($departures->last())->format('H:i');

        $placeholders = [
            'name' => $hotel->name,
            'address' => $hotel->address ?? '-',
            'phones' => $hotel->phones->map(fn($phone) => $phone->phone_number)->implode(', '),
            'tour_number' => $hotelItem['tour_number'],
            'guests' => $hotelItem['guests'],
            'arrivals' => $arrivalsStr,
            'departures' => $departuresStr,
            'total_nights' => $hotelItem['hotel_total_nights'] ?? 1,
            'roomings' => $roomAmounts->map(fn($amount, $roomType) => "$amount $roomType")->implode("\n"),
            'pax' => $hotelItem['pax'],
            'prices' => $prices->map(fn($price, $roomType) => "$roomType: " . TourService::formatMoney($price)
            )->implode("\n"),
            'total' => TourService::formatMoney($hotelItem['hotelTotalPrice']),
            'payment_method' => $hotelItem['payment_method'],

            'arrival_time' => $firstArrivalTime,
            'departure_time' => $lastDepartureTime,
        ];

        foreach ($placeholders as $placeholder => $value) {
            $templateProcessor->setValue($placeholder, $value);
        }

        return $templateProcessor;
    }

    public static function getNumberSuffix(int $number): string
    {
        $lastDigit = $number % 10;
        $lastTwoDigits = $number % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 13) {
            return 'th'; // Special case for numbers ending in 11, 12, or 13
        }

        return match ($lastDigit) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}

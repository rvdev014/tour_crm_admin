<?php

namespace App\Services;

use App\Enums\ExpenseType;
use App\Models\Tour;
use App\Models\TourDay;
use App\Models\TourDayExpense;
use App\Models\TourRoomType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\TemplateProcessor;

class ExportHotelService
{
    public static function getHotelsData(Tour $tour): Collection
    {
        $result = collect();

        $tourPax = $tour->getTotalPax();
        $groupNum = $tour->group_number;
        $countryName = $tour->country->name;
        $rooming = $tour->roomTypes->mapWithKeys(fn(TourRoomType $roomType) => [
            $roomType->roomType->name => $roomType->amount
        ]);

        $prevHotelExpense = null;

        /** @var Collection<TourDay> $tourDays */
        $tourDays = $tour->days()->orderBy('date')->get();
        foreach ($tourDays as $tourDay) {
            /** @var TourDayExpense $hotelExpense */
            $hotelExpense = $tourDay->expenses->first(
                fn(TourDayExpense $expense) => $expense->type === ExpenseType::Hotel
            );
            if (!$hotelExpense) {
                continue;
            }

            $date = $tourDay->date;
            $city = $tourDay->city->name;
            $hotelName = $hotelExpense->hotel->name;

            if ($hotelExpense->hotel_checkin_time) {
                $date->setTimeFromTimeString($hotelExpense->hotel_checkin_time);
            }

            $hotelItem = [
                'hotelName' => $hotelName,
                'date' => $date->format('d.m.Y H:i'),
                'city' => $city,
                'country' => $countryName,
                'pax' => $tourPax,
                'groupNum' => $groupNum,
                'hotelId' => $hotelExpense->hotel_id,
                'rooming' => $rooming,
                'arrivals' => [],
                'departures' => [],
            ];

            $existingHotel = $result->get($hotelExpense->hotel_id);
            if ($existingHotel) {
                // If the hotel is the same as the previous day, it's not an arrival or departure
                if ($prevHotelExpense && $prevHotelExpense['hotelId'] === $hotelExpense->hotel_id) {
                    continue;
                }

                // If the hotel is different from the previous day, but exists in the result, it's a new arrival
                $existingHotel['arrivals'][] = $date->format('d.m.Y H:i');
                $result->put($hotelExpense->hotel_id, $existingHotel);

                // And departure for previous hotel
                if ($prevHotelExpense) {
                    $prevHotel = $result->get($prevHotelExpense['hotelId']);
                    $prevHotel['departures'][] = $date->format('d.m.Y  00:00');
                    $result->put($prevHotelExpense['hotelId'], $prevHotel);
                }
            } else {
                // If the hotel is different from the previous day, it's a new hotel
                // Add departures to the previous hotel
                if ($prevHotelExpense) {
                    $prevHotel = $result->get($prevHotelExpense['hotelId']);
                    $prevHotel['departures'][] = $date->format('d.m.Y 00:00');
                    $result->put($prevHotelExpense['hotelId'], $prevHotel);
                }

                // First day of hotel
                $hotelItem['arrivals'][] = $date->format('d.m.Y H:i');
                $result->put($hotelExpense->hotel_id, $hotelItem);
            }

            $prevHotelExpense = $hotelItem;
        }

        $lastHotelItem = $result->last();
        if ($lastHotelItem) {
            $lastHotelItem['departures'][] = $tour->end_date->format('d.m.Y H:i');
            $result->put($lastHotelItem['hotelId'], $lastHotelItem);
        }

        return $result;
    }

    public static function getHotelsDataCorporate(Tour $tour): Collection
    {
        $result = collect();

        $tourPax = $tour->getTotalPax();
        $groupNum = $tour->group_number;
        $countryName = '-';
        $rooming = $tour->roomTypes->mapWithKeys(fn(TourRoomType $roomType) => [
            $roomType->roomType->name => $roomType->amount
        ]);

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

            if ($hotelExpense->hotel_checkin_time) {
                $date->setTimeFromTimeString($hotelExpense->hotel_checkin_time);
            }

            $hotelItem = [
                'hotelName' => $hotelName,
                'date' => $date->format('d.m.Y H:i'),
                'city' => $city,
                'country' => $countryName,
                'pax' => $tourPax,
                'groupNum' => $groupNum,
                'hotelId' => $hotelExpense->hotel_id,
                'rooming' => $rooming,
                'arrivals' => [],
                'departures' => [],
            ];

            $existingHotel = $result->get($hotelExpense->hotel_id);
            if ($existingHotel) {
                // If the hotel is the same as the previous day, it's not an arrival or departure
                if ($prevHotelExpense && $prevHotelExpense['hotelId'] === $hotelExpense->hotel_id) {
                    continue;
                }

                // If the hotel is different from the previous day, but exists in the result, it's a new arrival
                $existingHotel['arrivals'][] = $date->format('d.m.Y H:i');
                $result->put($hotelExpense->hotel_id, $existingHotel);

                // And departure for previous hotel
                if ($prevHotelExpense) {
                    $prevHotel = $result->get($prevHotelExpense['hotelId']);
                    $prevHotel['departures'][] = $date->format('d.m.Y  00:00');
                    $result->put($prevHotelExpense['hotelId'], $prevHotel);
                }
            } else {
                // If the hotel is different from the previous day, it's a new hotel
                // Add departures to the previous hotel
                if ($prevHotelExpense) {
                    $prevHotel = $result->get($prevHotelExpense['hotelId']);
                    $prevHotel['departures'][] = $date->format('d.m.Y 00:00');
                    $result->put($prevHotelExpense['hotelId'], $prevHotel);
                }

                // First day of hotel
                $hotelItem['arrivals'][] = $date->format('d.m.Y H:i');
                $result->put($hotelExpense->hotel_id, $hotelItem);
            }

            $prevHotelExpense = $hotelItem;
        }

        $lastHotelItem = $result->last();
        if ($lastHotelItem) {
            $lastHotelItem['departures'][] = $tour->end_date->format('d.m.Y H:i');
            $result->put($lastHotelItem['hotelId'], $lastHotelItem);
        }

        return $result;
    }

    public static function getTemplatePath(): string
    {
        return __DIR__ . '/Templates/Report_hotel.docx';
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public static function getReplacedTemplate($hotelItem): TemplateProcessor
    {
        $templateProcessor = new TemplateProcessor(self::getTemplatePath());

        $rooming = collect($hotelItem['rooming'] ?? []);
        $arrivals = collect($hotelItem['arrivals'] ?? []);
        $departures = collect($hotelItem['departures'] ?? []);

        $arrivalsStr = $arrivals->map(function ($arrival, $index) {
            $label = ++$index . ExportHotelService::getNumberSuffix($index) . " arrival\n";
            return $label . Carbon::parse($arrival)->format('d.m.Y');
        })->implode("\n\n");

        $arrivalTimesStr = $arrivals->map(fn ($arrival) => Carbon::parse($arrival)->format('H:i'))->implode("\n\n");
        $departuresStr = $departures->map(fn ($arrival) => Carbon::parse($arrival)->format('d.m.Y'))->implode("\n\n");

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
            'outsTime' => '',
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

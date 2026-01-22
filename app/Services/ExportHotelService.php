<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\Hotel;
use App\Models\TourDay;
use App\Models\HotelRule;
use App\Enums\ExpenseType;
use App\Models\TourRoomType;
use App\Enums\RoomPersonType;
use App\Models\HotelRoomType;
use App\Models\TourDayExpense;
use Illuminate\Support\Carbon;
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
                       $personType,
                       $companyId = null
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
                if ($companyId) {
                    $price = $hotelRoomType->getPriceWithPercent($companyId, $personType);
                } else {
                    $price = $hotelRoomType->getPrice($personType);
                }
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
        $companyId = $tour->company_id;
        $personType = ExpenseService::getPersonType($tour->country->id);
        $addPercent = 0;

        $roomAmounts = $tour->roomTypes->mapWithKeys(fn(TourRoomType $rt) => [$rt->roomType->name => $rt->amount]);
        $roomAmountsById = $tour->roomTypes->mapWithKeys(fn(TourRoomType $rt) => [$rt->roomType->id => $rt->amount]);

        /** @var Collection<TourDay> $tourDays */
        $tourDays = $tour->days()->orderBy('date')->get();
        foreach ($tourDays as $tourDay) {
            /** @var TourDayExpense $hotelExpense */
            $hotelExpenses = $tourDay->expenses->filter(fn(TourDayExpense $exp) => $exp->type === ExpenseType::Hotel);
            foreach ($hotelExpenses as $hotelExpense) {
                $date = $tourDay->date;
                $city = $tourDay->city->name;
                $hotel = $hotelExpense->hotel;
                $hotelPrices = self::getHotelPrices(
                    hotelExpense: $hotelExpense,
                    date: $date,
                    roomAmounts: $roomAmountsById,
                    personType: $personType,
                );
                
                if ($hotelExpense->hotel_checkin_time) {
                    $date->setTimeFromTimeString($hotelExpense->hotel_checkin_time);
                }
                
                $hotelItem = [
                    'tour_number' => $tour->group_number,
                    'payment_method' => $tour->payment_type?->getLabel(),
                    'hotel' => $hotelExpense->hotel,
                    'hotelId' => $hotelExpense->hotel_id,
                    'hotelName' => $hotel->name,
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
                        $date->clone()->setTimeFromTimeString($hotelExpense->hotel_checkout_time ?? '00:00')->format(
                            'd.m.Y H:i'
                        )
                    ],
                    'operator' => $tour->createdBy->name ?? '-',
                    'contract_number' => $hotel?->contract_number ?? null,
                    'contract_date' => $hotel?->contract_date ?? null,
                ];
                
                $result->put($hotelExpense->id, $hotelItem);
            }
        }

        return $result;
    }

    public static function getHotelsDataCorporate(Tour $tour): Collection
    {
        $result = collect();

        $tourPax = $tour->getTotalPax();
        $groupNum = $tour->group_number;
        $countryName = '-';
        $personType = RoomPersonType::Uzbek;

        $roomAmounts = $tour->roomTypes->mapWithKeys(fn(TourRoomType $rt) => [$rt->roomType->name => $rt->amount]);
        $roomAmountsById = $tour->roomTypes->mapWithKeys(fn(TourRoomType $rt) => [$rt->roomType->id => $rt->amount]);

        foreach ($tour->groups as $group) {
            /** @var TourDayExpense $hotelExpense */
            $hotelExpenses = $group->getExpenses(ExpenseType::Hotel);
            foreach ($hotelExpenses as $hotelExpense) {
                $date = $hotelExpense->date;
                $city = $hotelExpense->city?->name;
                $hotel = $hotelExpense->hotel;
                
                $hotelPrices = self::getHotelPrices(
                    hotelExpense: $hotelExpense,
                    date: $date,
                    roomAmounts: $roomAmountsById,
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
                    'hotelName' => $hotel?->name,
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
                        Carbon::parse($hotelExpense->hotel_checkout_date_time)->format('d.m.Y H:i')
                    ],
                    'operator' => $tour->createdBy->name ?? '-',
                    'contract_number' => $hotel?->contract_number ?? null,
                    'contract_date' => $hotel?->contract_date ?? null,
                ];
                
                $result->put($hotelExpense->id, $hotelItem);
            }
        }

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
        foreach (self::getPlaceholders($hotelItem) as $placeholder => $value) {
            $templateProcessor->setValue($placeholder, $value);
        }

        return $templateProcessor;
    }

    public static function getPlaceholders($hotelItem): array
    {
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

        /** @var Carbon $contractDate */
        $contractDate = $hotelItem['contract_date'];

        return [
            'date' => Carbon::parse($hotelItem['date'])->format('d-M'),
            'country' => $hotelItem['country'],
            'city' => $hotelItem['city'],
            'pax' => $hotelItem['pax'] . ' pax',
            'groupNum' => $hotelItem['groupNum'],
            'hotel' => $hotelItem['hotelName'],
            'rooming' => $rooming->map(fn($amount, $roomType) => "$roomType: $amount")->implode("\t\t\t"),
            'roomingArr' => $rooming,
            'arrivals' => $arrivalsStr,
            'arrivalTimes' => $arrivalTimesStr,
            'outs' => $departuresStr,
            'outsTime' => $departureTimesStr,
            'operator' => $hotelItem['operator'],
            'contract_num' => $hotelItem['contract_number'] ?? null,
            'contract_year' => $contractDate?->format('Y'),
            'contract_day' => $contractDate?->format('d'),
            'contract_month' => $contractDate?->locale('ru')->translatedFormat('F'),
            'contract_month_en' => $contractDate?->locale('en')->translatedFormat('F'),
        ];
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public static function getReplacedTemplateSecond($hotelItem, $passengerName = ''): TemplateProcessor
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
        
        [$rules_1_content, $rules_2_content, $rules_3_content] = self::getHotelRulesStr($hotel);
//        dd($rules);
        
        $placeholders = [
            'name' => $hotel->name,
            'guest_name' => $passengerName,
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
            'rules_1' => $rules_1_content,
            'rules_2' => $rules_2_content,
            'rules_3' => $rules_3_content,
        ];

        foreach ($placeholders as $placeholder => $value) {
            $templateProcessor->setValue($placeholder, $value);
        }

        return $templateProcessor;
    }

    public static function getHotelRulesStr(Hotel $hotel): array
    {
        $rule_1_content = '';
        $rule_2_content = '';
        $rule_3_content = '';
        
        $ruleIndex = 1;
        
        // Берем только первые три правила из коллекции
        // array_slice гарантирует, что мы итерируемся не более чем по 3 элементам.
        $firstThreeRules = $hotel->rules->slice(0, 3);
        
        foreach ($firstThreeRules as $rule) {
            
            // Переменные для текущего правила
            $title = '';
            $description = '';
            
            // --- 1. Логика Формирования Правил ---
            
            if ($rule->rule_type === HotelRule::TYPE_EARLY_CHECK_IN) {
                $startTime = substr($rule->start_time, 0, 5); // 06:00
                $endTime = substr($rule->end_time, 0, 5);     // 14:00
                
                if ($rule->impact_value == 100 && $rule->start_time === '00:00:00') {
                    $title = "Ранний заезд до {$endTime}";
                    $description = "оплачивается 100% стоимости номера\n(питание входит в стоимость)";
                } elseif ($rule->impact_value == 50) {
                    $title = "Ранний заезд после {$startTime} и до {$endTime}";
                    $description = "оплачивается 50% от стоимости номера\n(питание входит в стоимость)";
                }
            } elseif ($rule->rule_type === HotelRule::TYPE_LATE_CHECK_OUT) {
                $startTime = substr($rule->start_time, 0, 5); // 13:00
                $endTime = substr($rule->end_time, 0, 5);     // 23:00
                
                $title = "Поздний выезд после {$startTime} и до {$endTime}";
                
                if ($rule->price_impact_type === HotelRule::IMPACT_PERCENTAGE) {
                    // Используем значение из модели
                    $description = "почасовая оплата берется в размере {$rule->impact_value}%";
                } elseif ($rule->price_impact_type === HotelRule::IMPACT_HOURLY) {
                    $description = "почасовая оплата берется в размере {$rule->impact_value}%";
                }
            }
            
            // --- 2. Присвоение Контента Переменным ---
            
            // Форматируем содержимое: Сначала Заголовок, затем Новая строка (\n), затем Описание
            // (Убедитесь, что title не пустой, хотя по логике он всегда должен быть)
            if ($title) {
                $fullContent = "{$title}\n{$description}";
                
                switch ($ruleIndex) {
                    case 1:
                        $rule_1_content = $fullContent;
                        break;
                    case 2:
                        $rule_2_content = $fullContent;
                        break;
                    case 3:
                        $rule_3_content = $fullContent;
                        break;
                }
            }
            
            $ruleIndex++;
        }
        
        // Удаляем последние две новые строки, чтобы не было лишнего отступа в конце
        return [$rule_1_content, $rule_2_content, $rule_3_content];
    }
    
    public static function getNumberSuffix(int $number): string
    {
        $lastDigit = $number % 10;
        $lastTwoDigits = $number % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 13) {
            return 'th'; // Special case for numbers ending in 11, 12, or 13
        }

        return match ($lastDigit) {
            1       => 'st',
            2       => 'nd',
            3       => 'rd',
            default => 'th',
        };
    }
}

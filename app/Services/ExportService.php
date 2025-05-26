<?php

namespace App\Services;

use App\Enums\CurrencyEnum;
use App\Enums\ExpenseType;
use App\Enums\GuideType;
use App\Enums\TourType;
use App\Models\HotelRoomType;
use App\Models\Tour;
use App\Models\TourRoomType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportService
{
    public static function getExport(Tour $tour): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        [$startRow_1, $maxLetter_1, $maxRow_1] = ExportService::genFirstTable($sheet, $tour);

        if ($tour->type === TourType::Corporate) {
            [$startRow_2, $maxLetter_2, $maxRow_2] = ExportService::genSecondTableCorporate(
                $maxRow_1 + 2,
                $sheet,
                $tour
            );
        } else {
            [$startRow_2, $maxLetter_2, $maxRow_2] = ExportService::genSecondTable($maxRow_1 + 2, $sheet, $tour);
        }

        // get column index by letter
        $columnIndex = Coordinate::columnIndexFromString($maxLetter_2);
        [$startCell, $startRow_3, $maxLetter_3, $maxRow_3] = ExportService::getThirdTable(
            $columnIndex - 1,
            $maxRow_2 + 2,
            $sheet,
            $tour
        );

        $style = $sheet->getStyle('A1' . ':' . $maxLetter_2 . $maxRow_3);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $style->getFont()->setSize(9);
        $style->getFont()->setBold(true);
        $style->getFont()->setName('Century Gothic');

        foreach (range('A', $maxLetter_2) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheetStyle_1 = $sheet->getStyle('A1:' . $maxLetter_1 . $maxRow_1);
        $sheetStyle_1->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheetStyle_2 = $sheet->getStyle('A' . $startRow_2 + 1 . ':' . $maxLetter_2 . $maxRow_2);
        $sheetStyle_2->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheetStyle_3 = $sheet->getStyle($startCell . ':' . $maxLetter_2 . $maxRow_3);
        $sheetStyle_3->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheetStyle_3->getFill()->setFillType(Fill::FILL_SOLID);
        $sheetStyle_3->getFill()->getStartColor()->setARGB('A6A6A6');

        $sheetHotelStyle = $sheet->getStyle('A' . $startRow_2 + 4 . ':' . $maxLetter_2 . $startRow_2 + 4);
        $sheetHotelStyle->getFill()->setFillType(Fill::FILL_SOLID);
        $sheetHotelStyle->getFill()->getStartColor()->setARGB('FFFF71');

        $sheetHotelStyle = $sheet->getStyle('A' . $startRow_2 + 5 . ':' . $maxLetter_2 . $startRow_2 + 5);
        $sheetHotelStyle->getFill()->setFillType(Fill::FILL_SOLID);
        $sheetHotelStyle->getFill()->getStartColor()->setARGB('D6DCDA');

        $sheetHotelStyle = $sheet->getStyle('A' . $startRow_2 + 8 . ':' . $maxLetter_2 . $startRow_2 + 8);
        $sheetHotelStyle->getFill()->setFillType(Fill::FILL_SOLID);
        $sheetHotelStyle->getFill()->getStartColor()->setARGB('D6DCDA');

        return $spreadsheet;
    }

    public static function genFirstTable(Worksheet $sheet, Tour $tour): array
    {
        $exRate = ExpenseService::getUsdToUzsCurrency();
        $exRate = TourService::formatMoney($exRate->rate);

        if ($tour->type == TourType::Corporate) {
            $data = [
                ['Group', $tour->group_number],
                ['Manager', $tour->createdBy->name],
                ['Pax', $tour->passengers()->count()],
                ['FOC', $tour->leader_pax > 0 ? $tour->leader_pax : '0'],
                ['Ex.rate', $exRate],
            ];
        } else {
            $data = [
                ['Group', $tour->group_number],
                ['Manager', $tour->createdBy->name],
                ['Travel Dates', $tour->start_date->format('d') . '-' . $tour->end_date->format('d.m.y')],
                ['Pax', $tour->pax],
                ['FOC', $tour->leader_pax > 0 ? $tour->leader_pax : '0'],
                ['Ex.rate', $exRate],
            ];
        }

        $sheet->fromArray($data);

        return [
            1, // startRow
            self::letter(count($data[0])), // maxLetter
            count($data), // maxRow
        ];
    }

    public static function getThirdTable(string $startLetter, int $startRow, Worksheet $sheet, Tour $tour): array
    {
        $operator = "-";
        $profit = $tour->price_result - $tour->expenses_total;
        $operatorProfit = 0;
        $createdBy = $tour->createdBy;
        $operatorPercent = '0';
        if ($createdBy) {
            $operator = $createdBy->name;
            $operatorProfit = $profit * $createdBy->operator_percent_tps / 100;
            $operatorPercent = $createdBy->operator_percent_tps ?? '0';
        }

        $grandProfit = $profit - $operatorProfit;

        $currency = ExpenseService::getUsdToUzsCurrency();

        $payment = round($tour->price_result / $currency->rate, 2);
        $expensesTotal = round($tour->expenses_total / $currency->rate, 2);
        $profit = round($profit / $currency->rate, 2);
        $grandProfit = round($grandProfit / $currency->rate, 2);

        if ($tour->type == TourType::Corporate) {
            $data = [
                ['Payment', TourService::formatMoney($payment)],
                ['Expenses', TourService::formatMoney($expensesTotal)],
                ['Profit', TourService::formatMoney($profit)],
                ['Operator', $operator]
            ];
        } else {
            $data = [
                ['Payment', TourService::formatMoney($payment)],
                ['Expenses', TourService::formatMoney($expensesTotal)],
                ['Profit', TourService::formatMoney($profit)],
                [$operator . "($operatorPercent%)", TourService::formatMoney($operatorProfit)],
                ['Grand Profit', TourService::formatMoney($grandProfit)]
            ];
        }

        $startCell = self::letter($startLetter) . $startRow;
        $sheet->fromArray($data, null, $startCell);

        return [
            $startCell,
            $startRow, // startRow
            self::letter(count($data[0])), // maxLetter
            $startRow + count($data) - 1, // maxRow
        ];
    }

    public static function genSecondTable(int $startRow, Worksheet $sheet, Tour $tour): array
    {
        $addPercent = TourService::getCompanyAddPercent($tour->company_id);
        $personType = ExpenseService::getPersonType($tour->country_id);
        $currencyUsd = ExpenseService::getUsdToUzsCurrency();

        // TODO: person_type, season_type added
        $tourRoomTypes = $tour->roomTypes->map(fn(TourRoomType $roomType) => [
            'id' => $roomType->roomType->id,
            'name' => $roomType->roomType->name,
            'amount' => $roomType->amount,
        ]);
        $tourRoomTypesCount = max($tourRoomTypes->count(), 1);

        $days[] = ['value' => '', 'colspan' => 1];
        $hotels[] = ['value' => 'Hotel', 'colspan' => 1];
        $roomTypes[] = ['value' => 'type of rooms', 'colspan' => 1];
        $amounts[] = ['value' => 'NUMBER OF ROOM', 'colspan' => 1];
        $prices[] = ['value' => 'price per Room', 'colspan' => 1];
        $totals[] = ['value' => 'total', 'colspan' => 1];
        $guides[] = ['value' => 'GUIDE', 'colspan' => 1];
        $museums[] = ['value' => 'ENTRANCE', 'colspan' => 1];
        $transports[] = ['value' => 'TRANSPORT', 'colspan' => 1];
        $lunches[] = ['value' => 'Lunch', 'colspan' => 1];
        $dinners[] = ['value' => 'Dinner', 'colspan' => 1];
        $planes[] = ['value' => 'AIR TICKETS', 'colspan' => 1];
        $trains[] = ['value' => 'TRAIN TICKETS', 'colspan' => 1];
        $shows[] = ['value' => 'SHOW', 'colspan' => 1];
        $others[] = ['value' => 'Extra', 'colspan' => 1];
        $conferences[] = ['value' => 'Conference', 'colspan' => 1];

        $hotelsTotalSum = 0;
        $hotelsTotalUsd = 0;

        $guidesTotalSum = 0;
        $guidesTotalUsd = 0;

        $museumsTotalSum = 0;
        $museumsTotalUsd = 0;

        $transportsTotalSum = 0;
        $transportsTotalUsd = 0;

        $lunchesTotalSum = 0;
        $lunchesTotalUsd = 0;

        $dinnersTotalSum = 0;
        $dinnersTotalUsd = 0;

        $planesTotalSum = 0;
        $planesTotalUsd = 0;

        $trainsTotalSum = 0;
        $trainsTotalUsd = 0;

        $showsTotalSum = 0;
        $showsTotalUsd = 0;

        $othersTotalSum = 0;
        $othersTotalUsd = 0;

        $conferencesTotalSum = 0;
        $conferencesTotalUsd = 0;

        foreach ($tour->days as $tourDay) {
            // Days
            $days[] = ['value' => $tourDay->date->format('d.m.Y'), 'colspan' => $tourRoomTypesCount];

            // Hotels
            $hotelExpense = $tourDay->getExpense(ExpenseType::Hotel);
            $hotels[] = ['value' => $hotelExpense?->hotel?->name, 'colspan' => $tourRoomTypesCount];

            // Room types, Amount, Price, Total
            foreach ($tourRoomTypes as $roomType) {
                if (!$hotelExpense) {
                    $roomTypes[] = ['value' => '', 'colspan' => 1];
                    $amounts[] = ['value' => '', 'colspan' => 1];
                    $prices[] = ['value' => '', 'colspan' => 1];
                    $totals[] = ['value' => '', 'colspan' => 1];
                    continue;
                }

                $hotel = $hotelExpense->hotel;
                $seasonType = ExpenseService::getSeasonType($hotel, $tourDay->date);

                /** @var HotelRoomType $hotelRoomType */
                $hotelRoomType = $hotel->roomTypes()
                    ->where('room_type_id', $roomType['id'])
                    ->where('season_type', $seasonType)
                    ->first();

                $amount = $roomType['amount'] ?? 0;
                $price = $hotelRoomType?->getPrice($addPercent, $personType) ?? 0;
                $hotelTotal = $amount * $price;

                $roomTypes[] = ['value' => $roomType['name'], 'colspan' => 1];
                $amounts[] = ['value' => $amount, 'colspan' => 1];
                $prices[] = ['value' => $price > 0 ? TourService::formatMoney($price, currency: 'sum') : 0, 'colspan' => 1];
                $totals[] = ['value' => $hotelTotal > 0  ? TourService::formatMoney($hotelTotal, currency: 'sum') : 0, 'colspan' => 1];

                $hotelsTotalSum += $hotelTotal;
            }

            // Guides
            if ($tour->guide_type == GuideType::Escort) {
                $guides[] = ['value' => '0', 'colspan' => $tourRoomTypesCount];

                $guidesTotalSum = $tour->guide_price_result;
                $guidesTotalUsd = round($tour->guide_price_result / $currencyUsd->rate, 2);
            } else {
                $guideExpenses = $tourDay->getExpenses(ExpenseType::Guide);
                $guidesUsd = ExpenseService::calculateExpensesPrice($guideExpenses);
                $guidesSum = ExpenseService::calculateExpensesPrice($guideExpenses, false);
                $guidesView = ExpenseService::calculateExpensesPriceView($guideExpenses);
                $guides[] = ['value' => $guidesView, 'colspan' => $tourRoomTypesCount];

                $guidesTotalUsd += $guidesUsd;
                $guidesTotalSum += $guidesSum;
            }

            // Museums
            $museumExpenses = $tourDay->getExpenses(ExpenseType::Museum);
            $museumsUsd = ExpenseService::calculateExpensesPrice($museumExpenses);
            $museumsSum = ExpenseService::calculateExpensesPrice($museumExpenses, false);
            $museumsView = ExpenseService::calculateExpensesPriceView($museumExpenses);
            $museums[] = ['value' => $museumsView, 'colspan' => $tourRoomTypesCount];

            $museumsTotalUsd += $museumsUsd;
            $museumsTotalSum += $museumsSum;

            // Transports
            $transportExpenses = $tourDay->getExpenses(ExpenseType::Transport);
            $transportsUsd = ExpenseService::calculateExpensesPrice($transportExpenses);
            $transportsSum = ExpenseService::calculateExpensesPrice($transportExpenses, false);
            $transportsView = ExpenseService::calculateExpensesPriceView($transportExpenses);
            $transports[] = ['value' => $transportsView, 'colspan' => $tourRoomTypesCount];

            $transportsTotalUsd += $transportsUsd;
            $transportsTotalSum += $transportsSum;

            // Lunch
            $lunchExpenses = $tourDay->getExpenses(ExpenseType::Lunch);
            $lunchesUsd = ExpenseService::calculateExpensesPrice($lunchExpenses);
            $lunchesSum = ExpenseService::calculateExpensesPrice($lunchExpenses, false);
            $lunchesView = ExpenseService::calculateExpensesPriceView($lunchExpenses);
            $lunches[] = ['value' => $lunchesView, 'colspan' => $tourRoomTypesCount];

            $lunchesTotalUsd += $lunchesUsd;
            $lunchesTotalSum += $lunchesSum;

            // Dinner
            $dinnerExpenses = $tourDay->getExpenses(ExpenseType::Dinner);
            $dinnersUsd = ExpenseService::calculateExpensesPrice($dinnerExpenses);
            $dinnersSum = ExpenseService::calculateExpensesPrice($dinnerExpenses, false);
            $dinnersView = ExpenseService::calculateExpensesPriceView($dinnerExpenses);
            $dinners[] = ['value' => $dinnersView, 'colspan' => $tourRoomTypesCount];

            $dinnersTotalUsd += $dinnersUsd;
            $dinnersTotalSum += $dinnersSum;

            // Flight
            $planeExpenses = $tourDay->getExpenses(ExpenseType::Flight);
            $planesUsd = ExpenseService::calculateExpensesPrice($planeExpenses);
            $planesSum = ExpenseService::calculateExpensesPrice($planeExpenses, false);
            $planesView = ExpenseService::calculateExpensesPriceView($planeExpenses);
            $planes[] = ['value' => $planesView, 'colspan' => $tourRoomTypesCount];

            $planesTotalUsd += $planesUsd;
            $planesTotalSum += $planesSum;

            // Train
            $trainExpenses = $tourDay->getExpenses(ExpenseType::Train);
            $trainsUsd = ExpenseService::calculateExpensesPrice($trainExpenses);
            $trainsSum = ExpenseService::calculateExpensesPrice($trainExpenses, false);
            $trainsView = ExpenseService::calculateExpensesPriceView($trainExpenses);
            $trains[] = ['value' => $trainsView, 'colspan' => $tourRoomTypesCount];

            $trainsTotalUsd += $trainsUsd;
            $trainsTotalSum += $trainsSum;

            // Show
            $showExpense = $tourDay->getExpenses(ExpenseType::Show);
            $showsUsd = ExpenseService::calculateExpensesPrice($showExpense);
            $showsSum = ExpenseService::calculateExpensesPrice($showExpense, false);
            $showsView = ExpenseService::calculateExpensesPriceView($showExpense);
            $shows[] = ['value' => $showsView, 'colspan' => $tourRoomTypesCount];

            $showsTotalUsd += $showsUsd;
            $showsTotalSum += $showsSum;

            // Show
            $otherExpenses = $tourDay->getExpenses(ExpenseType::Extra);
            $othersUsd = ExpenseService::calculateExpensesPrice($otherExpenses);
            $othersSum = ExpenseService::calculateExpensesPrice($otherExpenses, false);
            $othersView = ExpenseService::calculateExpensesPriceView($otherExpenses);
            $others[] = ['value' => $othersView, 'colspan' => $tourRoomTypesCount];

            $othersTotalUsd += $othersUsd;
            $othersTotalSum += $othersSum;

            // Conference
            $confExpense = $tourDay->getExpenses(ExpenseType::Conference);
            $conferencesUsd = ExpenseService::calculateExpensesPrice($confExpense);
            $conferencesSum = ExpenseService::calculateExpensesPrice($confExpense, false);
            $conferencesView = ExpenseService::calculateExpensesPriceView($confExpense);
            $conferences[] = ['value' => $conferencesView, 'colspan' => $tourRoomTypesCount];

            $conferencesTotalUsd += $conferencesUsd;
            $conferencesTotalSum += $conferencesSum;
        }

        $hotelsTotalUsd = round($hotelsTotalSum / $currencyUsd?->rate, 2);

        $days[] = ['value' => '', 'colspan' => 1];
        $hotels[] = ['value' => 'SUM TOTAL', 'colspan' => 1];
        $roomTypes[] = ['value' => '', 'colspan' => 1];
        $amounts[] = ['value' => '', 'colspan' => 1];
        $prices[] = ['value' => '', 'colspan' => 1];
        $totals[] = ['value' => TourService::formatMoney($hotelsTotalSum), 'colspan' => 1];
        $guides[] = ['value' => TourService::formatMoney($guidesTotalSum), 'colspan' => 1];
        $museums[] = ['value' => TourService::formatMoney($museumsTotalSum), 'colspan' => 1];
        $transports[] = ['value' => TourService::formatMoney($transportsTotalSum), 'colspan' => 1];
        $lunches[] = ['value' => TourService::formatMoney($lunchesTotalSum), 'colspan' => 1];
        $dinners[] = ['value' => TourService::formatMoney($dinnersTotalSum), 'colspan' => 1];
        $planes[] = ['value' => TourService::formatMoney($planesTotalSum), 'colspan' => 1];
        $trains[] = ['value' => TourService::formatMoney($trainsTotalSum), 'colspan' => 1];
        $shows[] = ['value' => TourService::formatMoney($showsTotalSum), 'colspan' => 1];
        $others[] = ['value' => TourService::formatMoney($othersTotalSum), 'colspan' => 1];
        $conferences[] = ['value' => TourService::formatMoney($conferencesTotalSum), 'colspan' => 1];

        $days[] = ['value' => '', 'colspan' => 1];
        $hotels[] = ['value' => 'USD TOTAL', 'colspan' => 1];
        $roomTypes[] = ['value' => '', 'colspan' => 1];
        $amounts[] = ['value' => '', 'colspan' => 1];
        $prices[] = ['value' => '', 'colspan' => 1];
        $totals[] = ['value' => TourService::formatMoney($hotelsTotalUsd), 'colspan' => 1];
        $guides[] = ['value' => TourService::formatMoney($guidesTotalUsd), 'colspan' => 1];
        $museums[] = ['value' => TourService::formatMoney($museumsTotalUsd), 'colspan' => 1];
        $transports[] = ['value' => TourService::formatMoney($transportsTotalUsd), 'colspan' => 1];
        $lunches[] = ['value' => TourService::formatMoney($lunchesTotalUsd), 'colspan' => 1];
        $dinners[] = ['value' => TourService::formatMoney($dinnersTotalUsd), 'colspan' => 1];
        $planes[] = ['value' => TourService::formatMoney($planesTotalUsd), 'colspan' => 1];
        $trains[] = ['value' => TourService::formatMoney($trainsTotalUsd), 'colspan' => 1];
        $shows[] = ['value' => TourService::formatMoney($showsTotalUsd), 'colspan' => 1];
        $others[] = ['value' => TourService::formatMoney($othersTotalUsd), 'colspan' => 1];
        $conferences[] = ['value' => TourService::formatMoney($conferencesTotalUsd), 'colspan' => 1];

        $result[] = $days;
        $result[] = $hotels;
        $result[] = $roomTypes;
        $result[] = $amounts;
        $result[] = $prices;
        $result[] = $totals;
        $result['separator_1'] = [];
        $result[] = $guides;
        $result[] = $museums;
        $result['separator_2'] = [];
        $result[] = $transports; // not completed
        $result['separator_3'] = [];
        $result[] = $lunches;
        $result[] = $dinners;
        $result['separator_4'] = [];
        $result[] = $planes;
        $result[] = $trains;
        $result['separator_5'] = [];
        $result[] = $shows;

        if ($tour->type == TourType::Corporate) {
            $result[] = $conferences;
        }

        $result[] = $others;
        $result['separator_6'] = [];

        $columnSpanSum = 0;
        $firstElem = reset($result);
        foreach ($firstElem as $value) {
            $columnSpanSum += $value['colspan'] ?? 0;
        }
        $maxLetter = self::letter($columnSpanSum);

        $titleRowStart = "A" . $startRow + 1;
        $titleRowEnd = $maxLetter . $startRow + 2;

        $sheet->setCellValue($titleRowStart, 'G R O U P    E X P E N C E S');
        $sheet->mergeCells("$titleRowStart:$titleRowEnd");
        $sheet->getStyle("$titleRowStart:$titleRowEnd")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'A6A6A6', // Light blue background
                ],
            ],
        ]);

        $separatorRows = [];
        $rowIndex = $startRow + 3;
        foreach ($result as $rowKey => $rows) {
            if (str_starts_with($rowKey, 'separator')) {
                $separatorRows[] = [
                    'rowIndex' => $rowIndex,
                    'coordinate' => "A$rowIndex:$maxLetter$rowIndex",
                ];
            }

            $colIndex = 1;
            foreach ($rows as $column) {
                if ($column['colspan'] > 1) {
                    $mergeCells = [];
                    for ($j = 1; $j <= $column['colspan']; $j++) {
                        $cellCoordinate = self::letter($colIndex) . $rowIndex;
                        $sheet->setCellValue($cellCoordinate, $column['value']);
                        $mergeCells[] = $cellCoordinate;
                        $colIndex++;
                    }
                    $sheet->mergeCells(reset($mergeCells) . ':' . end($mergeCells));
                } else {
                    $cellCoordinate = self::letter($colIndex) . $rowIndex;
                    $sheet->setCellValue($cellCoordinate, $column['value']);
                    $colIndex++;
                }
            }
            $rowIndex++;
        }

        for ($i = 1; $i < ($columnSpanSum - 1); $i++) {
            $sheet->setCellValue(self::letter($i) . $rowIndex, '');
        }

        $totalAllSum = $hotelsTotalSum + $guidesTotalSum + $museumsTotalSum + $transportsTotalSum + $lunchesTotalSum + $dinnersTotalSum + $planesTotalSum + $trainsTotalSum + $showsTotalSum + $othersTotalSum + $conferencesTotalSum;
        $totalAllUsd = $hotelsTotalUsd + $guidesTotalUsd + $museumsTotalUsd + $transportsTotalUsd + $lunchesTotalUsd + $dinnersTotalUsd + $planesTotalUsd + $trainsTotalUsd + $showsTotalUsd + $othersTotalUsd + $conferencesTotalUsd;

        $sheet->setCellValue(self::letter($columnSpanSum - 1) . $rowIndex, TourService::formatMoney($totalAllSum));
        $sheet->setCellValue(self::letter($columnSpanSum) . $rowIndex, TourService::formatMoney($totalAllUsd));
        $sheet->mergeCells("A$rowIndex:" . self::letter($columnSpanSum - 2) . $rowIndex);

        $totalRowRange = "A$rowIndex:" . self::letter($columnSpanSum) . $rowIndex;
        $sheet->getStyle($totalRowRange)->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle($totalRowRange)->getFill()->getStartColor()->setARGB('FFFF71');

        foreach ($separatorRows as $separatorRow) {
            $sheet->mergeCells($separatorRow['coordinate']);
            $sheet->getRowDimension($separatorRow['rowIndex'])->setRowHeight(7);
            $sheet->getStyle($separatorRow['coordinate'])->getFill()->setFillType(Fill::FILL_SOLID);
            $sheet->getStyle($separatorRow['coordinate'])->getFill()->getStartColor()->setARGB('BFBFBF');
        }

        return [
            $startRow, // startRow
            $maxLetter, // maxLetter
            $startRow + count($result) + 1 + 2, // maxRow
        ];
    }

    public static function genSecondTableCorporate(int $startRow, Worksheet $sheet, Tour $tour): array
    {
        $addPercent = TourService::getCompanyAddPercent($tour->company_id);
        $personType = ExpenseService::getPersonType($tour->country_id);

        $tourRoomTypes = $tour->roomTypes->map(fn(TourRoomType $roomType) => [
            'id' => $roomType->roomType->id,
            'name' => $roomType->roomType->name,
            'amount' => $roomType->amount,
        ]);
        $tourRoomTypesCount = max($tourRoomTypes->count(), 1);

        $days[] = ['value' => '', 'colspan' => 1];
        $hotels[] = ['value' => 'Hotel', 'colspan' => 1];
        $roomTypes[] = ['value' => 'type of rooms', 'colspan' => 1];
        $amounts[] = ['value' => 'NUMBER OF ROOM', 'colspan' => 1];
        $prices[] = ['value' => 'price per Room', 'colspan' => 1];
        $totals[] = ['value' => 'total', 'colspan' => 1];
        $guides[] = ['value' => 'GUIDE', 'colspan' => 1];
        $museums[] = ['value' => 'ENTRANCE', 'colspan' => 1];
        $transports[] = ['value' => 'TRANSPORT', 'colspan' => 1];
        $lunches[] = ['value' => 'Lunch', 'colspan' => 1];
        $dinners[] = ['value' => 'Dinner', 'colspan' => 1];
        $planes[] = ['value' => 'AIR TICKETS', 'colspan' => 1];
        $trains[] = ['value' => 'TRAIN TICKETS', 'colspan' => 1];
        $shows[] = ['value' => 'SHOW', 'colspan' => 1];
        $others[] = ['value' => 'Extra', 'colspan' => 1];
        $conferences[] = ['value' => 'Conference', 'colspan' => 1];

        $hotelsTotal = 0;
        $guidesTotal = 0;
        $museumsTotal = 0;
        $transportsTotal = 0;
        $lunchesTotal = 0;
        $dinnersTotal = 0;
        $planesTotal = 0;
        $trainsTotal = 0;
        $showsTotal = 0;
        $othersTotal = 0;
        $conferencesTotal = 0;

        $dates = $tour->expenses->pluck('date')->unique();

        foreach ($dates as $date) {
            // Hotels
            $hotelExpense = $tour->getExpenseByDate($date, ExpenseType::Hotel);
            if (!$hotelExpense) {
                $tourRoomTypesCount = 1;
            }

            $hotels[] = ['value' => $hotelExpense?->hotel?->name, 'colspan' => $tourRoomTypesCount];

            // Days
            $days[] = ['value' => $date->format('d.m.Y'), 'colspan' => $tourRoomTypesCount];

            // Guides
            if ($tour->guide_type == GuideType::Escort) {
                $guides[] = ['value' => '', 'colspan' => $tourRoomTypesCount];
                $guidesTotal = $tour->guide_price;
            } else {
                $guideExpenses = $tour->getExpensesByDate($date, ExpenseType::Guide);
                $guides[] = ['value' => $guideExpenses->sum('price'), 'colspan' => $tourRoomTypesCount];
                $guidesTotal += $guideExpenses->sum('price') ?? 0;
            }

            // Museums
            $museumExpense = $tour->getExpensesByDate($date, ExpenseType::Museum);
            $museums[] = ['value' => $museumExpense->sum('price'), 'colspan' => $tourRoomTypesCount];
            $museumsTotal += $museumExpense->sum('price') ?? 0;

            // Transports
            $transportExpenses = $tour->getExpensesByDate($date, ExpenseType::Transport);
            $transports[] = ['value' => $transportExpenses->sum('price'), 'colspan' => $tourRoomTypesCount];
            $transportsTotal += $transportExpenses->sum('price') ?? 0;

            // Lunch
            $lunchExpenses = $tour->getExpensesByDate($date, ExpenseType::Lunch);
            $lunches[] = ['value' => $lunchExpenses->sum('price'), 'colspan' => $tourRoomTypesCount];
            $lunchesTotal += $lunchExpenses->sum('price') ?? 0;

            // Dinner
            $dinnerExpenses = $tour->getExpensesByDate($date, ExpenseType::Dinner);
            $dinners[] = ['value' => $dinnerExpenses->sum('price'), 'colspan' => $tourRoomTypesCount];
            $dinnersTotal += $dinnerExpenses->sum('price') ?? 0;

            // Flight
            $planeExpenses = $tour->getExpensesByDate($date, ExpenseType::Flight);
            $planes[] = ['value' => $planeExpenses->sum('price'), 'colspan' => $tourRoomTypesCount];
            $planesTotal += $planeExpenses->sum('price') ?? 0;

            // Train
            $trainExpenses = $tour->getExpensesByDate($date, ExpenseType::Train);
            $trains[] = ['value' => $trainExpenses->sum('price'), 'colspan' => $tourRoomTypesCount];
            $trainsTotal += $trainExpenses->sum('price') ?? 0;

            // Show
            $showExpense = $tour->getExpensesByDate($date, ExpenseType::Show);
            $shows[] = ['value' => $showExpense->sum('price'), 'colspan' => $tourRoomTypesCount];
            $showsTotal += $showExpense->sum('price') ?? 0;

            // Show
            $otherExpenses = $tour->getExpensesByDate($date, ExpenseType::Extra);
            $others[] = ['value' => $otherExpenses->sum('price'), 'colspan' => $tourRoomTypesCount];
            $othersTotal += $otherExpenses->sum('price') ?? 0;

            // Conference
            $confExpense = $tour->getExpensesByDate($date, ExpenseType::Conference);
            $conferences[] = ['value' => $confExpense->sum('price'), 'colspan' => $tourRoomTypesCount];
            $conferencesTotal += $confExpense->sum('price') ?? 0;

            if (!$hotelExpense) {
                $roomTypes[] = ['value' => '', 'colspan' => 1];
                $amounts[] = ['value' => '', 'colspan' => 1];
                $prices[] = ['value' => '', 'colspan' => 1];
                $totals[] = ['value' => '', 'colspan' => 1];
                continue;
            }

            // Room types, Amount, Price, Total
            foreach ($tourRoomTypes as $roomType) {
                $hotel = $hotelExpense->hotel;
                $seasonType = ExpenseService::getSeasonType($hotel, $hotelExpense->date);

                /** @var HotelRoomType $hotelRoomType */
                $hotelRoomType = $hotel->roomTypes()
                    ->where('room_type_id', $roomType['id'])
                    ->where('season_type', $seasonType)
                    ->first();

                $amount = $roomType['amount'] ?? 0;
                $price = $hotelRoomType?->getPrice($addPercent, $personType) ?? 0;
                $hotelTotal = $amount * $price;

                $roomTypes[] = ['value' => $roomType['name'], 'colspan' => 1];
                $amounts[] = ['value' => $amount, 'colspan' => 1];
                $prices[] = ['value' => $price, 'colspan' => 1];
                $totals[] = ['value' => $hotelTotal, 'colspan' => 1];

                $hotelsTotal += $hotelTotal;
            }
        }

        $days[] = ['value' => '', 'colspan' => 1];
        $hotels[] = ['value' => 'SUM TOTAL', 'colspan' => 1];
        $roomTypes[] = ['value' => '', 'colspan' => 1];
        $amounts[] = ['value' => '', 'colspan' => 1];
        $prices[] = ['value' => '', 'colspan' => 1];
        $totals[] = ['value' => $hotelsTotal, 'colspan' => 1];

        $guides[] = ['value' => $guidesTotal, 'colspan' => 1];
        $museums[] = ['value' => $museumsTotal, 'colspan' => 1];
        $transports[] = ['value' => $transportsTotal, 'colspan' => 1];
        $lunches[] = ['value' => $lunchesTotal, 'colspan' => 1];
        $dinners[] = ['value' => $dinnersTotal, 'colspan' => 1];
        $planes[] = ['value' => $planesTotal, 'colspan' => 1];
        $trains[] = ['value' => $trainsTotal, 'colspan' => 1];
        $shows[] = ['value' => $showsTotal, 'colspan' => 1];
        $others[] = ['value' => $othersTotal, 'colspan' => 1];
        $conferences[] = ['value' => $conferencesTotal, 'colspan' => 1];

        $result[] = $days;
        $result[] = $hotels;
        $result[] = $roomTypes;
        $result[] = $amounts;
        $result[] = $prices;
        $result[] = $totals;
        $result['separator_1'] = [];
        $result[] = $guides;
        $result[] = $museums;
        $result['separator_2'] = [];
        $result[] = $transports; // not completed
        $result['separator_3'] = [];
        $result[] = $lunches;
        $result[] = $dinners;
        $result['separator_4'] = [];
        $result[] = $planes;
        $result[] = $trains;
        $result['separator_5'] = [];
        $result[] = $shows;

        if ($tour->type == TourType::Corporate) {
            $result[] = $conferences;
        }

        $result[] = $others;
        $result['separator_6'] = [];

        $columnSpanSum = 0;
        $firstElem = reset($result);
        foreach ($firstElem as $value) {
            $columnSpanSum += $value['colspan'] ?? 0;
        }
        $maxLetter = self::letter($columnSpanSum);

        $titleRowStart = "A" . $startRow + 1;
        $titleRowEnd = $maxLetter . $startRow + 2;

        $sheet->setCellValue($titleRowStart, 'G R O U P    E X P E N C E S');
        $sheet->mergeCells("$titleRowStart:$titleRowEnd");
        $sheet->getStyle("$titleRowStart:$titleRowEnd")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'A6A6A6', // Light blue background
                ],
            ],
        ]);

        $separatorRows = [];
        $rowIndex = $startRow + 3;
        foreach ($result as $rowKey => $rows) {
            if (str_starts_with($rowKey, 'separator')) {
                $separatorRows[] = [
                    'rowIndex' => $rowIndex,
                    'coordinate' => "A$rowIndex:$maxLetter$rowIndex",
                ];
            }

            $colIndex = 1;
            foreach ($rows as $column) {
                if ($column['colspan'] > 1) {
                    $mergeCells = [];
                    for ($j = 1; $j <= $column['colspan']; $j++) {
                        $cellCoordinate = self::letter($colIndex) . $rowIndex;
                        $sheet->setCellValue($cellCoordinate, $column['value']);
                        $mergeCells[] = $cellCoordinate;
                        $colIndex++;
                    }
                    $sheet->mergeCells(reset($mergeCells) . ':' . end($mergeCells));
                } else {
                    $cellCoordinate = self::letter($colIndex) . $rowIndex;
                    $sheet->setCellValue($cellCoordinate, $column['value']);
                    $colIndex++;
                }
            }
            $rowIndex++;
        }

        for ($i = 1; $i < ($columnSpanSum - 1); $i++) {
            $sheet->setCellValue(self::letter($i) . $rowIndex, '');
        }

        $totalAll = $hotelsTotal + $guidesTotal + $museumsTotal + $transportsTotal + $lunchesTotal + $dinnersTotal + $planesTotal + $trainsTotal + $showsTotal + $othersTotal + $conferencesTotal;
        $sheet->setCellValue(self::letter($columnSpanSum) . $rowIndex, $totalAll);
        $sheet->mergeCells("A$rowIndex:" . self::letter($columnSpanSum - 1) . $rowIndex);

        $totalRowRange = "A$rowIndex:" . self::letter($columnSpanSum) . $rowIndex;
        $sheet->getStyle($totalRowRange)->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle($totalRowRange)->getFill()->getStartColor()->setARGB('FFFF71');

        foreach ($separatorRows as $separatorRow) {
            $sheet->mergeCells($separatorRow['coordinate']);
            $sheet->getRowDimension($separatorRow['rowIndex'])->setRowHeight(7);
            $sheet->getStyle($separatorRow['coordinate'])->getFill()->setFillType(Fill::FILL_SOLID);
            $sheet->getStyle($separatorRow['coordinate'])->getFill()->getStartColor()->setARGB('BFBFBF');
        }

        return [
            $startRow, // startRow
            $maxLetter, // maxLetter
            $startRow + count($result) + 1 + 2, // maxRow
        ];
    }

    public static function letter(int|string $number): string
    {
        return Coordinate::stringFromColumnIndex($number);
    }
}

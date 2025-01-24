<?php

namespace App\Services;

use App\Enums\ExpenseType;
use App\Models\Tour;
use App\Models\TourDay;
use App\Models\TourDayExpense;
use App\Models\TourRoomType;
use Illuminate\Database\Eloquent\Collection;
use NumberFormatter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExportClientService
{
    public static function getExport(Tour $tour): Spreadsheet
    {
        $templateFile = __DIR__ . '/Templates/Invoice_client.xlsx';

        // Load the template file
        $spreadsheet = IOFactory::load($templateFile);
        $sheet = $spreadsheet->getActiveSheet();

        $allExpenses = $tour->days->flatMap(fn(TourDay $day) => $day->expenses);

        /** @var TourDayExpense $planeExpense */
        $planeExpense = $allExpenses->first(fn(TourDayExpense $expense) => $expense->type == ExpenseType::Plane);

        /** @var Collection<TourDayExpense> $extraExpenses */
        $extraExpenses = $allExpenses->filter(fn(TourDayExpense $expense) => $expense->type == ExpenseType::Extra);

        $expenseTotal = $tour->expenses;
        $planePriceTotal = $planeExpense?->price;
        $extraPriceTotal = $extraExpenses->sum('price');

        $pax = $tour->pax + $tour->leader_pax;
        $paxPriceTotal = $expenseTotal - $planePriceTotal;
        $paxPrice = $paxPriceTotal / $pax;

        $planePax = $pax;
        $planePrice = $planePriceTotal / $planePax;

        $extraPax = $tour->leader_pax;
        $extraPrice = $extraPriceTotal / $extraPax;

        $tourLeadersPrice = $paxPrice + $planePrice;
        $tourLeadersPriceTotal = $tourLeadersPrice * $tour->leader_pax;

        $dueTotal = $expenseTotal - $tourLeadersPriceTotal;
        $dueTotalWithWords = self::getPriceWithWords($dueTotal);

        $expensesList = self::getExpensesList($tour);

        $flightInfo = "-";
        if ($planeExpense?->fromCity && $planeExpense?->toCity) {
            $flightInfo = $planeExpense->fromCity->name . ' - ' . $planeExpense->toCity->name . ' flight';
        }

        $roomTypes = $tour->roomTypes->mapWithKeys(fn(TourRoomType $roomType) => [$roomType->roomType->name => $roomType->amount]);
        $roomTypes = $roomTypes->merge([
            'sda' => 2,
            '11212' => 34,
            'wqw' => 3,
        ]);

        $placeholders = [
            '{date}' => now()->format('m/d/Y'),
            '{groupNumber}' => $tour->group_number,
            '{personsCount}' => $tour->pax . '+' . $tour->leader_pax . ' FOC',
            '{arrivalDate}' => $tour->start_date->format('m/d/Y'),
            '{departureDate}' => $tour->end_date->format('m/d/Y'),
            '{expensesList}' => $expensesList->implode("\n"),

            '{flightInfo}' => $flightInfo,
            '{cityFrom}' => $planeExpense?->fromCity->name,
            '{cityTo}' => $planeExpense?->toCity->name,

            '{pax}' => $pax,
            '{paxPrice}' => $paxPrice,
            '{paxPriceTotal}' => $paxPriceTotal,

            '{planePax}' => $planePax,
            '{planePrice}' => $planePrice,
            '{planePriceTotal}' => $planePriceTotal,

            '{extraPax}' => $extraPax,
            '{extraPrice}' => $extraPrice,
            '{extraPriceTotal}' => $extraPriceTotal,

            '{priceTotal}' => $expenseTotal,

            '{tourLeadersPrice}' => $tourLeadersPrice,
            '{tourLeadersCount}' => $tour->leader_pax,
            '{tourLeaderPriceTotal}' => $tourLeadersPrice * $tour->leader_pax,

            '{dueTotal}' => $expenseTotal - $tourLeadersPriceTotal,
            '{dueTotalWithWords}' => ucfirst($dueTotalWithWords),

            '{rooming}' => $roomTypes->map(fn($amount, $roomType) => "$roomType - $amount")->implode("\n"),
        ];

        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $cellValue = $cell->getValue();
                if (is_string($cellValue)) {
                    // Replace placeholders in the cell value
                    $newValue = str_replace(array_keys($placeholders), array_values($placeholders), $cellValue);
                    $cell->setValue($newValue);
                }
            }
        }

        $sheet->getRowDimension(17)->setRowHeight(15 * $expensesList->count());
        $sheet->getStyle('J13:K15')->getAlignment()->setWrapText(true);

        $roomingHeight = 15 * $roomTypes->count() / 3;
        $sheet->getRowDimension(13)->setRowHeight($roomingHeight);
        $sheet->getRowDimension(14)->setRowHeight($roomingHeight);
        $sheet->getRowDimension(15)->setRowHeight($roomingHeight);

        return $spreadsheet;
    }

    public static function getExpensesList(Tour $tour): \Illuminate\Support\Collection
    {
        return $tour->days->flatMap(function (TourDay $day) {
            return $day->expenses->map(fn(TourDayExpense $expense) => "* " . $expense->type->getLabel());
        });
    }

    public static function getPriceWithWords(int $price): string
    {
        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return $f->format($price);
    }
}

<?php

namespace App\Services;

use App\Enums\ExpenseType;
use App\Models\Tour;
use App\Models\TourDay;
use App\Models\TourDayExpense;
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
        $planePriceTotal = $planeExpense->price;
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

        $placeholders = [
            '{date}' => now()->format('m/d/Y'),
            '{groupNumber}' => $tour->group_number,
            '{personsCount}' => $tour->pax . '-' . $tour->leader_pax . ' FOC',
            '{arrivalDate}' => $tour->start_date->format('m/d/Y'),
            '{departureDate}' => $tour->end_date->format('m/d/Y'),
            '{expensesList}' => self::getExpensesList($tour),
            '{cityFrom}' => $planeExpense->fromCity->name,
            '{cityTo}' => $planeExpense->toCity->name,

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
            '{dueTotalWithWords}' => 'Twenty-three thousand four hundred thirteen',
        ];

        $roomTypes = [
            'Double/Twin' => 15,
            'Single' => 5,
            'Triple' => 5,
        ];

        $i = 0;
        foreach ($roomTypes as $label => $roomType) {
            $sheet->setCellValue("J" . (13 + $i), $label);
            $sheet->setCellValue("K" . (13 + $i), $roomType);
            $i++;
        }

        return $spreadsheet;
    }

    public static function getExpensesList(Tour $tour): string
    {
        $expenses = $tour->days->flatMap(function (TourDay $day) {
            return $day->expenses->map(fn(TourDayExpense $expense) => "* " . $expense->type->getLabel());
        });

        return $expenses->implode("\n");
    }

    public static function getPriceWithWords(int $price): string
    {
        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return $f->format($price);
    }
}

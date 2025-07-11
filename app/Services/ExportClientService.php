<?php

namespace App\Services;

use App\Enums\ExpenseType;
use App\Models\Tour;
use App\Models\TourDay;
use App\Models\TourDayExpense;
use App\Models\TourRoomType;
use PhpOffice\PhpSpreadsheet\Exception;
use Illuminate\Database\Eloquent\Collection;
use NumberFormatter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExportClientService
{
    /**
     * @throws Exception
     */
    public static function getExport(Tour $tour): Spreadsheet
    {
        $templateFile = __DIR__ . '/Templates/Invoice_client.xlsx';

        // Load the template file
        $spreadsheet = IOFactory::load($templateFile);
        $sheet = $spreadsheet->getActiveSheet();

        /** @var \Illuminate\Support\Collection $allExpenses */
        $allExpenses = $tour->days->flatMap(fn(TourDay $day) => $day->expenses);

        /** @var TourDayExpense $planeExpense */
        $planeExpense = $allExpenses->first(fn(TourDayExpense $expense) => $expense->type == ExpenseType::Flight);

        /** @var Collection<TourDayExpense> $extraExpenses */
        $extraExpenses = $allExpenses->filter(fn(TourDayExpense $expense) => $expense->type == ExpenseType::Extra);

        $currencyUsd = ExpenseService::getUsdToUzsCurrency();

        $pax = max(1, $tour->pax + $tour->leader_pax);
        $paxPriceTotal = round($tour->total_price / $currencyUsd->rate, 2);
        $paxPrice = round($paxPriceTotal / $pax, 2);

        $extraPax = $pax;
        $extraPriceTotal = ExpenseService::calculateExpensesPrice($extraExpenses);
        $extraPrice = round($extraPriceTotal / $extraPax, 2);

        $tourLeaderPax = $tour->leader_pax ?? 0;
        $tourLeadersPrice = $paxPrice;
        $tourLeadersPriceTotal = ($tourLeadersPrice + $tour->single_supplement_price) * $tourLeaderPax;

        $priceTotal = $paxPriceTotal + $extraPriceTotal;

        $dueTotal = $priceTotal - $tourLeadersPriceTotal;
        $dueTotalWithWords = ucfirst(self::getPriceWithWords($dueTotal));

        $expensesList = $allExpenses->groupBy(fn(TourDayExpense $expense) => $expense->type->getLabel())
            ->map(fn($expenses, string $type) => "* $type");

        $flightInfo = "-";
        if ($planeExpense?->fromCity && $planeExpense?->toCity) {
            $flightInfo = $planeExpense->fromCity->name . ' - ' . $planeExpense->toCity->name . ' flight';
        }

        $roomTypes = $tour->roomTypes->mapWithKeys(
            fn(TourRoomType $roomType) => [$roomType->roomType->name => $roomType->amount]
        );

        $placeholders = [
            '{date}' => now()->format('d.m.Y'),
            '{groupNumber}' => $tour->group_number,
            '{personsCount}' => $tour->pax . '+' . "$tourLeaderPax" . ' FOC',
            '{arrivalDate}' => $tour->start_date->format('d.m.Y'),
            '{departureDate}' => $tour->end_date->format('d.m.Y'),
            '{expensesList}' => $expensesList->implode("\n"),

            '{company}' => $tour->company->name,
            '{package}' => $tour->package_name,
            '{flightInfo}' => $flightInfo,
            '{cityFrom}' => $planeExpense?->fromCity?->name,
            '{cityTo}' => $planeExpense?->toCity?->name,

            '{pax}' => $pax,
            '{paxPrice}' => $paxPrice,
            '{paxPriceTotal}' => $paxPriceTotal,

//            '{planePax}' => $planePax,
//            '{planePrice}' => $planePrice,
//            '{planePriceTotal}' => $planePriceTotal,

            '{extraNames}' => $extraExpenses->map(fn(TourDayExpense $expense) => $expense->other_name)->implode(", "),
            '{extraPax}' => $extraPax,
            '{extraPrice}' => $extraPrice,
            '{extraPriceTotal}' => $extraPriceTotal,

            '{priceTotal}' => $priceTotal,

            '{tourLeadersPrice}' => $tourLeadersPrice,
            '{tourLeadersCount}' => "$tourLeaderPax",
            '{tourLeaderPriceTotal}' => "$tourLeadersPriceTotal",

            '{dueTotal}' => $dueTotal,
            '{dueTotalWithWords}' => $dueTotalWithWords,

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

        $sheet->getRowDimension(17)->setRowHeight(max(40, 15 * ($expensesList->count() + 1)));
        $sheet->getStyle('J13:K15')->getAlignment()->setWrapText(true);
        $sheet->getStyle('J13:K15')->getFont()->setItalic(true);

        $roomingHeight = max(15, 15 * $roomTypes->count() / 3);
        $sheet->getRowDimension(13)->setRowHeight($roomingHeight);
        $sheet->getRowDimension(14)->setRowHeight($roomingHeight);
        $sheet->getRowDimension(15)->setRowHeight($roomingHeight);

        if (empty($tourLeaderPax) || $tourLeaderPax == 0) {
//            $sheet->removeRow(19);
//            $sheet->removeRow(20, 2);
            $sheet->removeRow(20);
        }

        return $spreadsheet;
    }

    public static function getExpensesList(Tour $tour): \Illuminate\Support\Collection
    {
        return $tour->days
            ->groupBy(fn(TourDay $day) => $day->date->format('d.m.Y'))
            ->flatMap(function (TourDay $day) {
                return $day->expenses->map(fn(TourDayExpense $expense) => "* " . $expense->type->getLabel());
            });
    }

    public static function getPriceWithWords(int $price): string
    {
        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return $f->format($price);
    }
}

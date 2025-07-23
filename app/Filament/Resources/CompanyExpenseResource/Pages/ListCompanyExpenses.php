<?php

namespace App\Filament\Resources\CompanyExpenseResource\Pages;

use App\Enums\CurrencyEnum;
use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Filament\Resources\CompanyExpenseResource;
use App\Models\Driver;
use App\Models\TourDayExpense;
use App\Services\TourService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCompanyExpenses extends ListRecords
{
    protected static string $resource = CompanyExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_all')
                ->label('Export All')
                ->icon('heroicon-o-document-text')
                ->requiresConfirmation(true)
                ->action(fn () => $this->exportExpenses()),
            ExportAction::make()
                ->requiresConfirmation()
                ->exports([
                    ExcelExport::make()
                        ->withFilename(fn () => $this->generateExportFilename())
                        ->fromTable()
                        ->withColumns([
                            Column::make('group_number')
                                ->heading('Group number')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                                    return $tour->group_number;
                                }),
                        ])
                ]),
            Actions\CreateAction::make(),
        ];
    }

    public function exportExpenses(): StreamedResponse
    {
        $filters = $this->table->getFiltersForm()->getState(); // Get current filters
        $filters = Arr::get($filters, 'filters', []);

        /** @var Collection<TourDayExpense> $expenses */
        $expenses = TourDayExpense::query()
            ->with([
                'tourGroup' => fn($query) => $query->with([
                    'tour' => fn($q) => $q->with('company'),
                    'passengers'
                ]),
                'tourDay' => fn($query) => $query->with(['tour' => fn($q) => $q->with('company')]),
            ])
            ->get();

        $spreadsheet = new Spreadsheet();

        $groupData = [];
        foreach ($expenses as $expense) {
            $groupData[$expense->type->getLabel()][] = $expense;
        }

        $headers = [
            'group_number' => 'Group Number',
            'company' => 'Company',
            'inn' => 'Company Inn',
            'start_date' => 'Start Date',
            'expense_date' => 'Expense Date',
            'passengers' => 'Passengers FIO',
            'expense_type' => 'Expense Type',
            'expense_name' => 'Expense Name',
            'tour_pax' => 'Pax',
            'route' => 'Route',
            'price' => 'Price',
            'payment_status' => 'Payment Status',
            'invoice_status' => 'Invoice Status',
        ];

        $headerLabels = array_values($headers);

        $sheetIndex = 0;
        foreach ($groupData as $type => $expenses) {
            if (empty($expenses)) {
                continue;
            }

            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet($sheetIndex);
            $sheet->getDefaultColumnDimension()->setWidth(15);
            $sheet->setTitle($type);

            $sheet->fromArray($headerLabels, null, 'A1');

            $values = [];
            foreach ($expenses as $expense) {
                $tour = $expense->tourGroup?->tour ?? $expense->tourDay->tour;
                $company = $tour->company;
                $date = $expense->tourDay->date ?? $expense->date;
                $fromCity = $expense->tourDay?->city?->name ?? $expense->city?->name;

                if ($expense->tourGroup) {
                    $pax = $expense->tourGroup->passengers()->count();
                } else {
                    $pax = $tour->getTotalPax();
                }

                $values[] = [
                    'group_number' => $tour->group_number,
                    'company' => $company?->name,
                    'inn' => $company?->inn,
                    'start_date' => $tour->start_date?->format('d.m.Y H:i'),
                    'expense_date' => $date?->format('d.m.Y'),
                    'passengers' => $expense->tourGroup?->passengers?->first()?->name ?? '-',
                    'expense_type' => $expense->type->getLabel(),
                    'expense_name' => match ($expense->type) {
                        ExpenseType::Hotel => $expense->hotel?->name,
                        ExpenseType::Museum => TourService::getMuseumsByIds([1, 2])->values()->join(', '),
                        ExpenseType::Lunch, ExpenseType::Dinner => $expense->restaurant?->name,
                        ExpenseType::Train => $expense->train?->name,
                        ExpenseType::Show => $expense->show?->name,
                        default => '',
                    },
                    'tour_pax' => $pax,
                    'route' => match ($expense->type) {
                        ExpenseType::Transport => $expense->transport_route,
                        ExpenseType::Flight => $expense->plane_route,
                        ExpenseType::Train => "$fromCity - {$expense->toCity?->name}",
                        default => '',
                    },
                    'price' => TourService::formatMoney($expense->price_result) . ' ' . CurrencyEnum::UZS->getSymbol(),
                    'payment_status' => $expense->payment_status?->getLabel(),
                    'invoice_status' => $expense->invoice_status?->getLabel(),
                ];
            }

            $sheet->fromArray($values, null, 'A2');
            $sheetIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $filename = $this->generateExportFilename($filters);

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    protected function generateExportFilename($filters = null): string
    {
        if (empty($filters)) {
            $filters = $this->table->getFiltersForm()->getState(); // Get current filters
            $filters = Arr::get($filters, 'filters', []);
        }

        $tourType = Arr::get($filters, 'tour_type');
        $tourType = $tourType ? TourType::tryFrom($tourType)?->getLabel() : null;

        $dateFrom = Arr::get($filters, 'date_from');
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom)?->format('dmy') : null;

        $dateUntil = Arr::get($filters, 'date_until');
        $dateUntil = $dateUntil ? Carbon::parse($dateUntil)?->format('dmy') : null;

        $filenameParts = ['expenses'];
        if ($tourType) {
            $filenameParts[] = strtolower($tourType);
        }
        if ($dateFrom) {
            $filenameParts[] = $dateFrom;
        }
        if ($dateUntil) {
            $filenameParts[] = $dateUntil;
        }

        return implode('_', $filenameParts) . '.xlsx';
    }
}

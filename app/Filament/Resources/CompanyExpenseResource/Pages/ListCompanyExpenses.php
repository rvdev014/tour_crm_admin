<?php

namespace App\Filament\Resources\CompanyExpenseResource\Pages;

use App\Enums\TourType;
use App\Filament\Resources\CompanyExpenseResource;
use App\Models\Driver;
use App\Models\TourDayExpense;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListCompanyExpenses extends ListRecords
{
    protected static string $resource = CompanyExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

    protected function generateExportFilename(): string
    {
        $filters = $this->table->getFiltersForm()->getState(); // Get current filters
        $filters = Arr::get($filters, 'filters', []);

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

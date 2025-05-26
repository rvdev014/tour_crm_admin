<?php

namespace App\Filament\Resources\CompanyIncomeResource\Pages;

use App\Filament\Resources\CompanyIncomeResource;
use App\Models\Driver;
use App\Models\TourDayExpense;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListCompanyExpenses extends ListRecords
{
    protected static string $resource = CompanyIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->requiresConfirmation()
                ->exports([
                    ExcelExport::make()->fromTable()->withColumns([
                        Column::make('group_number')->heading('Group number')->getStateUsing(function (TourDayExpense $record) {
                            $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                            return $tour->group_number;
                        }),
                    ])
                ]),
            Actions\CreateAction::make(),
        ];
    }
}

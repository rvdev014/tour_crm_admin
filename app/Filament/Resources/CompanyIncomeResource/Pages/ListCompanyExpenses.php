<?php

namespace App\Filament\Resources\CompanyIncomeResource\Pages;

use App\Enums\CurrencyEnum;
use App\Enums\TourType;
use App\Filament\Resources\CompanyIncomeResource;
use App\Models\Tour;
use App\Services\TourService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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
                    ExcelExport::make()
                        ->withFilename(fn () => $this->generateExportFilename())
                        ->fromTable()
                        ->withColumns([
                            Column::make('group_number')
                                ->heading('Group number')
                                ->getStateUsing(function (Tour $record) {
                                    return $record->group_number;
                                }),
                            Column::make('company')
                                ->heading('Company')
                                ->getStateUsing(function (Tour $record) {
                                    return $record->company->name;
                                }),
                            Column::make('inn')
                                ->heading('Company Inn')
                                ->getStateUsing(function (Tour $record) {
                                    return $record->company->inn;
                                }),
                            Column::make('tour_pax')
                                ->heading('Pax')
                                ->getStateUsing(function (Tour $record) {
                                    return $record->getTotalPax();
                                }),
                            Column::make('price')
                                ->heading('Price')
                                ->getStateUsing(function (Tour $record) {
                                    if ($record->isCorporate()) {
                                        $price = $record->expenses_total;
                                    } else {
                                        $price = $record->total_price;
                                    }
                                    return TourService::formatMoney($price) . ' ' . CurrencyEnum::UZS->getSymbol();
                                }),
                            Column::make('payment_status')
                                ->heading('Payment Status')
                                ->getStateUsing(function (Tour $record) {
                                    return $record->payment_status?->getLabel();
                                }),
                            Column::make('start_date')
                                ->heading('Start Date')
                                ->getStateUsing(function (Tour $record) {
                                    return $record->start_date?->format('d.m.Y H:i');
                                }),
                            Column::make('end_date')
                                ->heading('End Date')
                                ->getStateUsing(function (Tour $record) {
                                    return $record->end_date?->format('d.m.Y H:i');
                                }),
                            Column::make('created_at')
                                ->heading('Created At')
                                ->getStateUsing(function (Tour $record) {
                                    return $record->created_at?->format('d.m.Y H:i');
                                }),
                        ])
                ]),
            Actions\CreateAction::make(),
        ];
    }

    protected function generateExportFilename(): string
    {
        $filters = $this->table->getFiltersForm()->getState(); // Get current filters
        $filters = Arr::get($filters, 'company', []);

        $tourType = Arr::get($filters, 'tour_type');
        $tourType = $tourType ? TourType::tryFrom($tourType)?->getLabel() : null;

        $dateFrom = Arr::get($filters, 'date_from');
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom)?->format('dmy') : null;

        $dateUntil = Arr::get($filters, 'date_until');
        $dateUntil = $dateUntil ? Carbon::parse($dateUntil)?->format('dmy') : null;

        $filenameParts = ['incomes'];
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

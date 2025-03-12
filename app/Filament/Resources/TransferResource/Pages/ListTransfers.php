<?php

namespace App\Filament\Resources\TransferResource\Pages;

use App\Filament\Resources\TransferResource;
use App\Models\Driver;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListTransfers extends ListRecords
{
    protected static string $resource = TransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->requiresConfirmation()
                ->exports([
                    ExcelExport::make()->fromTable()->withColumns([
                        Column::make('date_time')->heading('Date & Time')->formatStateUsing(function ($state) {
                            return $state->format('d.m.Y H:i');
                        }),
                        Column::make('driver_ids')->heading('Drivers')->formatStateUsing(function ($state) {
                            if (empty($state)) {
                                return '-';
                            }

                            $driver = Driver::query()->find($state);
                            return $driver?->name ?? '-';
                        }),
                    ])
                ]),
            Actions\CreateAction::make(),
        ];
    }
}

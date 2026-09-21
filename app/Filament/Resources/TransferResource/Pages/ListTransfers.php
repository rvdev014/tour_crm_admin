<?php

namespace App\Filament\Resources\TransferResource\Pages;

use App\Filament\Resources\TransferResource;
use App\Models\Driver;
use App\Models\Transfer;
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
                    // Explicit columns, one field each, instead of ->fromTable(): the list packs two values into one
                    // cell (number + tour, date + time, ...) and renders the date as HTML. The export inherits a
                    // table column's formatting, so generating it from the table would put markup into the file and
                    // drop the second values. Still uses the table's query, so the filters and search apply.
                    ExcelExport::make()
                        ->useTableQuery()
                        ->withColumns([
                            Column::make('number')->heading(__('Number')),

                            Column::make('tour')
                                ->heading(__('Tour'))
                                ->getStateUsing(fn (Transfer $record) => TransferResource::tourGroupNumber($record)),

                            Column::make('date')
                                ->heading(__('Date'))
                                ->getStateUsing(fn (Transfer $record) => $record->date_time?->format('d.m.Y')),

                            Column::make('time')
                                ->heading(__('Time'))
                                ->getStateUsing(fn (Transfer $record) => $record->date_time?->format('H:i')),

                            Column::make('company')
                                ->heading(__('Company'))
                                ->getStateUsing(fn (Transfer $record) => $record->company?->name),

                            Column::make('requested_by')->heading(__('Requested by')),

                            Column::make('route')->heading(__('Destination')),

                            Column::make('city')
                                ->heading(__('Location'))
                                ->getStateUsing(fn (Transfer $record) => $record->toCity?->name),

                            Column::make('pax')->heading(__('Pax')),

                            Column::make('nameplate')->heading(__('Табличка')),

                            Column::make('driver_ids')
                                ->heading(__('Drivers'))
                                ->formatStateUsing(function ($state) {
                                    if (empty($state)) {
                                        return '-';
                                    }

                                    // $state is the driver_ids ARRAY; find($array) returns a Collection, so the
                                    // old `$driver?->name` was always null and the column always printed '-'.
                                    return Driver::query()->whereIn('id', (array) $state)->pluck('name')->join(', ') ?: '-';
                                }),

                            Column::make('driver_name')->heading(__('Driver name')),

                            Column::make('driver_phone')
                                ->heading(__('Driver phone number'))
                                ->getStateUsing(fn (Transfer $record) => TransferResource::phoneAsText($record->driver_phone)),

                            Column::make('driver_status')
                                ->heading(__('Driver status'))
                                ->getStateUsing(fn (Transfer $record) => empty($record->driver_ids) ? null : $record->effectiveDriverStatus()->getLabel()),

                            Column::make('status')
                                ->heading(__('Status'))
                                ->getStateUsing(fn (Transfer $record) => $record->status?->getLabel()),

                            Column::make('sell_price')->heading(__('Sell price')),

                            Column::make('buy_price')->heading(__('Buy price')),

                            Column::make('created_by')
                                ->heading(__('Created by'))
                                ->getStateUsing(fn (Transfer $record) => $record->createdBy?->name),

                            Column::make('created_at')
                                ->heading(__('Created'))
                                ->getStateUsing(fn (Transfer $record) => $record->created_at?->format('d.m.Y H:i')),
                        ]),
                ]),
            Actions\CreateAction::make(),
        ];
    }
}

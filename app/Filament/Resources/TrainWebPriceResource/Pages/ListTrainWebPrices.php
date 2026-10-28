<?php

namespace App\Filament\Resources\TrainWebPriceResource\Pages;

use App\Filament\Resources\TrainWebPriceResource;
use App\Services\TrainWebPriceImportService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ListTrainWebPrices extends ListRecords
{
    protected static string $resource = TrainWebPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upload')
                ->label(__('Upload Excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->modalDescription(__('Replaces ALL current website train prices with the file contents. Columns: Train, From, To, Economy, Business, VIP. One row covers both directions.'))
                ->form([
                    FileUpload::make('file')
                        ->label(__('Excel file'))
                        ->disk('local')
                        ->directory('train-price-uploads')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->required(),
                ])
                ->action(function (array $data, TrainWebPriceImportService $importer) {
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $result = $importer->import($path);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->title(__('Nothing was imported'))
                            ->body(nl2br(e($e->getMessage())))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    Notification::make()
                        ->title(__('Prices updated'))
                        ->body(__(':routes routes on :trains trains.', $result))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('template')
                ->label(__('Download current prices / template'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (TrainWebPriceImportService $importer) => response()
                    ->download($importer->export(), 'train-prices-for-website.xlsx')
                    ->deleteFileAfterSend()),

            Actions\CreateAction::make(),
        ];
    }
}

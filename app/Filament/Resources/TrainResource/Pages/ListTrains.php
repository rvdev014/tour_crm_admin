<?php

namespace App\Filament\Resources\TrainResource\Pages;

use App\Filament\Resources\TrainResource;
use App\Filament\Resources\TrainWebPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrains extends ListRecords
{
    protected static string $resource = TrainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Prices for the website's "guaranteed tickets" live on their own page, separate
            // from the tour-costing tariffs edited per train here.
            Actions\Action::make('website_prices')
                ->label(__('Website prices'))
                ->icon('heroicon-o-globe-alt')
                ->color('gray')
                ->url(TrainWebPriceResource::getUrl())
                ->visible(fn () => TrainWebPriceResource::canViewAny()),
            Actions\CreateAction::make(),
        ];
    }
}

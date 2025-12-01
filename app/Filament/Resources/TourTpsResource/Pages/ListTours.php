<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Filament\Resources\TourTpsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTours extends ListRecords
{
    protected static string $resource = TourTpsResource::class;
    protected static ?string $title = 'TPS';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

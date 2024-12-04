<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Filament\Resources\TourCorporateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTours extends ListRecords
{
    protected static string $resource = TourCorporateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

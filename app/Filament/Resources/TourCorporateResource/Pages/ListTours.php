<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Filament\Resources\TourCorporateResource;
use Closure;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTours extends ListRecords
{
    protected static string $resource = TourCorporateResource::class;
    protected static ?string $title = 'Tours Corporate';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\TourTpsTestResource\Pages;

use App\Filament\Resources\TourTpsResource;
use App\Filament\Resources\TourTpsTestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTours extends ListRecords
{
    protected static string $resource = TourTpsTestResource::class;
    protected static ?string $title = 'TPS';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

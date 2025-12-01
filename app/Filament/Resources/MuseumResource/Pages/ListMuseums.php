<?php

namespace App\Filament\Resources\MuseumResource\Pages;

use App\Filament\Resources\MuseumResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMuseums extends ListRecords
{
    protected static string $resource = MuseumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

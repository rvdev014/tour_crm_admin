<?php

namespace App\Filament\Resources\WebTourResource\Pages;

use App\Filament\Resources\WebTourResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWebTours extends ListRecords
{
    protected static string $resource = WebTourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

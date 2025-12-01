<?php

namespace App\Filament\Resources\WebTourRequestResource\Pages;

use App\Filament\Resources\WebTourRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListWebTourRequests extends ListRecords
{
    protected static string $resource = WebTourRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action as per requirements
        ];
    }
}
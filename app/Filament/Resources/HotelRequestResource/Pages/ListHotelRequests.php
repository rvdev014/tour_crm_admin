<?php

namespace App\Filament\Resources\HotelRequestResource\Pages;

use App\Filament\Resources\HotelRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHotelRequests extends ListRecords
{
    protected static string $resource = HotelRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

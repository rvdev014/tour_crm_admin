<?php

namespace App\Filament\Resources\TransportClassResource\Pages;

use App\Filament\Resources\TransportClassResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTransportClasses extends ManageRecords
{
    protected static string $resource = TransportClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

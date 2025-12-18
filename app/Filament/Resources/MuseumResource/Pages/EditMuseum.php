<?php

namespace App\Filament\Resources\MuseumResource\Pages;

use App\Filament\Resources\MuseumResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMuseum extends EditRecord
{
    protected static string $resource = MuseumResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


    protected function getHeaderActions(): array
    {
        return [
//            Actions\DeleteAction::make(),
        ];
    }
}

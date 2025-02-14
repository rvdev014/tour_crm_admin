<?php

namespace App\Filament\Resources\MuseumResource\Pages;

use App\Filament\Resources\MuseumResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMuseum extends CreateRecord
{
    protected static string $resource = MuseumResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

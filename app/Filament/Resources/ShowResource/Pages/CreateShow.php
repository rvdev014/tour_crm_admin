<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateShow extends CreateRecord
{
    protected static string $resource = ShowResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

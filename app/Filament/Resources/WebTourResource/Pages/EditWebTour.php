<?php

namespace App\Filament\Resources\WebTourResource\Pages;

use App\Filament\Resources\WebTourResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWebTour extends EditRecord
{
    protected static string $resource = WebTourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

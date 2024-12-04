<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Filament\Resources\TourCorporateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourCorporateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

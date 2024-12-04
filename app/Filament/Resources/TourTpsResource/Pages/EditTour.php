<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Filament\Resources\TourTpsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourTpsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

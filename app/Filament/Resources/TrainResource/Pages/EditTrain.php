<?php

namespace App\Filament\Resources\TrainResource\Pages;

use App\Filament\Resources\TrainResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrain extends EditRecord
{
    protected static string $resource = TrainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

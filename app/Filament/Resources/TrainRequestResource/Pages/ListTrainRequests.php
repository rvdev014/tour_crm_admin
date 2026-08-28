<?php

namespace App\Filament\Resources\TrainRequestResource\Pages;

use App\Filament\Resources\TrainRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrainRequests extends ListRecords
{
    protected static string $resource = TrainRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

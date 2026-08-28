<?php

namespace App\Filament\Resources\TrainRequestResource\Pages;

use App\Filament\Resources\TrainRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrainRequest extends EditRecord
{
    protected static string $resource = TrainRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // if status field changed, set status_updated_by to auth user
        if ($this->record->status?->value !== $data['status']) {
            $data['status_updated_by'] = auth()->id();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\DeleteAction::make(),
        ];
    }
}

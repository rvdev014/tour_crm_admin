<?php

namespace App\Filament\Resources\FlightRequestResource\Pages;

use App\Filament\Resources\FlightRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFlightRequest extends EditRecord
{
    protected static string $resource = FlightRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // if status field changed, set status_updated_by to auth user
        // (loose comparison: the form submits status as a string, the enum's value is an int)
        if ($this->record->status?->value != $data['status']) {
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

<?php

namespace App\Filament\Resources\WebTourRequestResource\Pages;

use App\Filament\Resources\WebTourRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWebTourRequest extends EditRecord
{
    protected static string $resource = WebTourRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->status?->value != $data['status']) {
            $data['status_updated_by'] = auth()->id();
        }
        
        return $data;
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
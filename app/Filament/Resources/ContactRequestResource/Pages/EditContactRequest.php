<?php

namespace App\Filament\Resources\ContactRequestResource\Pages;

use App\Filament\Resources\ContactRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactRequest extends EditRecord
{
    protected static string $resource = ContactRequestResource::class;
    
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

<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Enums\CompanyType;
use App\Filament\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['type'] == CompanyType::TPS->value) {
            $data['inn'] = null;
            $data['additional_percent'] = null;
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

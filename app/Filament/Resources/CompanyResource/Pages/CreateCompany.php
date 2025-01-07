<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Enums\CompanyType;
use App\Filament\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['type'] == CompanyType::TPS->value) {
            $data['inn'] = null;
            $data['additional_percent'] = null;
        }

        return $data;
    }
}

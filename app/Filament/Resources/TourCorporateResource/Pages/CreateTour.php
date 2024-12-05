<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Enums\TourType;
use App\Filament\Resources\TourCorporateResource;
use App\Services\TourService;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourCorporateResource::class;
    protected static ?string $title = 'Create Tour Corporate';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = TourType::Corporate;
        $data['group_number'] = TourService::getGroupNumber(TourType::Corporate);
        $data['created_by'] = auth()->id();

        return $data;
    }
}

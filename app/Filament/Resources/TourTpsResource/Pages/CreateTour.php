<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\TourType;
use App\Filament\Resources\TourTpsResource;
use App\Services\TourService;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourTpsResource::class;
    protected static ?string $title = 'Create Tour TPS';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = TourType::TPS;
        $data['group_number'] = TourService::getGroupNumber(TourType::TPS);
        $data['created_by'] = auth()->id();

        return $data;
    }
}

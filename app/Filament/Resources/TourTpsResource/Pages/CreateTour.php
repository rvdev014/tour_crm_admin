<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Filament\Resources\TourTpsResource;
use App\Models\Transfer;
use App\Services\TourService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourTpsResource::class;
    protected static ?string $title = 'Create Tour TPS';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = TourType::TPS;
        $data['group_number'] = TourService::getGroupNumber(TourType::TPS);
        $data['created_by'] = auth()->id();

        $days = collect($this->form->getRawState()['days'] ?? []);
        $totalExpenses = $days->flatMap(fn($day) => $day['expenses'])->sum('price');

        $data['expenses'] = $totalExpenses;
        $data['income'] = $data['price'] - $totalExpenses;

        return $data;
    }
}

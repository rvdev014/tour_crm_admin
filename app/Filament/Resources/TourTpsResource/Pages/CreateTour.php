<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\ExpenseStatus;
use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Filament\Resources\TourTpsResource;
use App\Models\TourRoomType;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourTpsResource::class;
    protected static ?string $title = 'Create Tour TPS';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $formState = $this->form->getRawState();
        $allExpenses = ExpenseService::mutateExpenses($formState);
        $totalExpenses = $allExpenses->sum('price') + ($data['guide_price'] ?? 0);

        $data['type'] = TourType::TPS;
        $data['created_by'] = auth()->id();
        $data['status'] = ExpenseService::getTourStatus($allExpenses);
        $data['expenses_total'] = $totalExpenses;
        $data['income'] = $data['price'] - $totalExpenses;

        TourService::sendMails($data, $formState['days'] ?? []);

        return $data;
    }

    protected function afterCreate(): void
    {
        ExpenseService::createTourRoomTypes($this->record->id, $this->form->getRawState());
    }
}

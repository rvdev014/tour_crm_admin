<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Enums\ExpenseStatus;
use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Filament\Resources\TourCorporateResource;
use App\Models\TourRoomType;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourCorporateResource::class;
    protected static ?string $title = 'Create Tour Corporate';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $formState = $this->form->getRawState();
        $allExpenses = ExpenseService::mutateExpenses($formState, isCorporate: true);

        $data['type'] = TourType::Corporate;
        $data['created_by'] = auth()->id();
        $data['status'] = ExpenseService::getTourStatus($allExpenses);
        $data['expenses_total'] = $allExpenses->sum('price');

        TourService::sendMails($formState, $allExpenses, isCorporate: true);

        TourService::sendTelegram($formState, isCorporate: true);

        return $data;
    }

    protected function afterCreate(): void
    {
        ExpenseService::createTourRoomTypes($this->record->id, $this->form->getRawState());
    }
}

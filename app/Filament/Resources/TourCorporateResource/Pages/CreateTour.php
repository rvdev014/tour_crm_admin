<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
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
        $data['expenses_total'] = ExpenseService::calculateAllExpensesPrice($allExpenses);

//        TourService::sendMails($formState, $allExpenses, isCorporate: true);

        return $data;
    }

    protected function afterCreate(): void
    {
//        $formState = $this->form->getRawState();
//        foreach ($formState['expenses'] as $expense) {
//            if ($expense['type'] == ExpenseType::Hotel->value) {
//                ExpenseService::createTourDayExpenseRoomTypes($expense['id'], $expense);
//            }
//        }
        TourService::sendTelegram($this->form->getRawState(), isCorporate: true);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

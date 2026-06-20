<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Enums\TourType;
use App\Filament\Resources\TourCorporateResource;
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

        $data['type'] = TourType::Corporate;
        $data['created_by'] = auth()->id();
        $data['group_number'] = $data['group_number'] ?? TourService::getGroupNumber(TourType::Corporate);

        $allExpenses = ExpenseService::getAllExpensesCorporateBasic($formState);
        $data['status'] = ExpenseService::getTourStatus($allExpenses);

        $expensesTotal = ExpenseService::calculateAllExpensesPrice($allExpenses);
        $data['expenses_total'] = $expensesTotal;

        return $data;
    }

    protected function afterCreate(): void
    {
        TourService::sendTelegram($this->form->getRawState(), isCorporate: true);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

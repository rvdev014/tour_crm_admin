<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Filament\Resources\TourCorporateResource;
use App\Models\Company;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourCorporateResource::class;
    protected static ?string $title = 'Create Tour Corporate';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $formState = $this->form->getRawState();

        $this->validateHotelExpensesGroupConfig($data['company_id'] ?? null, $formState);

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

    private function validateHotelExpensesGroupConfig(?int $companyId, array $formState): void
    {
        if (!$companyId) {
            return;
        }

        $hasHotelExpense = false;
        foreach ($formState['groups'] ?? [] as $group) {
            foreach ($group['expenses'] ?? [] as $expense) {
                if (($expense['type'] ?? null) == ExpenseType::Hotel->value) {
                    $hasHotelExpense = true;
                    break 2;
                }
            }
        }

        if (!$hasHotelExpense) {
            return;
        }

        /** @var Company $company */
        $company = Company::find($companyId);
        if (!$company?->group_id) {
            Notification::make()
                ->title('Hotel Expense Error')
                ->body("Company \"{$company?->name}\" has no Group configured. Hotel expenses cannot be saved without a Group.")
                ->danger()
                ->persistent()
                ->send();
            $this->halt();
        }
    }
}

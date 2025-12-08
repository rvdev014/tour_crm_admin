<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Enums\ExpenseType;
use App\Filament\Resources\TourCorporateResource;
use App\Filament\Resources\TourTpsResource\Actions\SendMailAction;
use App\Models\Tour;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourCorporateResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $formState = $this->form->getRawState();

        $allExpenses = ExpenseService::getAllExpensesCorporateBasic($formState);
        $data['status'] = ExpenseService::getTourStatus($allExpenses);
        
//        $expensesTotal = ExpenseService::updateExpensesPricesCorporate($this->record, $data);
        
        $expensesTotal = 0;
        foreach ($allExpenses as $expense) {
            $expensePrice = $expense['price_converted'] ?? $expense['price'] ?? 0;
            $expensesTotal += $expensePrice;
        }
        $data['expenses_total'] = $expensesTotal;

        return $data;
    }

    protected function afterSave(): void
    {
        TourService::sendTelegram($this->form->getRawState(), isCorporate: true, isUpdated: true);
    }

    protected function getHeaderActions(): array
    {
        /** @var Tour $tour */
        $tour = $this->record;
        return [
            SendMailAction::make('mail_hotel')
                ->tour($tour)
                ->type('hotels')
                ->label('Mail Hotels'),
            Actions\Action::make('export_all')
                ->label('Export All')
                ->icon('heroicon-o-document-text')
                ->url(route('export-all', $this->record)),
            Actions\DeleteAction::make()
                ->label('Delete')
                ->icon('heroicon-o-trash'),
        ];
    }

    /*protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }*/
}

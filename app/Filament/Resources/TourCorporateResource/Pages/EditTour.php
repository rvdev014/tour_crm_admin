<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Models\Tour;
use Filament\Actions;
use App\Services\TourService;
use App\Services\ExpenseService;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\TourCorporateResource;
use App\Filament\Resources\TourTpsResource\Actions\SendMailAction;

class EditTour extends EditRecord
{
    protected static string $resource = TourCorporateResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $formState = $this->form->getRawState();
        $allExpenses = ExpenseService::mutateExpenses($formState, isCorporate: true);

        $data['status'] = ExpenseService::getTourStatus($allExpenses);
        $data['expenses_total'] = ExpenseService::calculateAllExpensesPrice($allExpenses);

        ExpenseService::updateTourRoomTypes($this->record->id, $data);

        //        TourService::sendMails($formState, $allExpenses, isCorporate: true);
        //        TourService::sendTelegram($formState, isCorporate: true);

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
            Actions\DeleteAction::make()
                ->label('Delete')
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Filament\Resources\TourTpsResource;
use App\Filament\Resources\TourTpsResource\Actions\SendMailAction;
use App\Models\Tour;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourTpsResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $formState = $this->form->getRawState();
        $allExpenses = ExpenseService::mutateExpenses($formState);

        ExpenseService::convertExpensePrice($data, 'price');
        ExpenseService::convertExpensePrice($data, 'guide_price');
        $totalExpenses = ExpenseService::calculateAllExpensesPrice($allExpenses) + ($data['guide_price_converted'] ?? $data['guide_price'] ?? 0);

        $price = $data['price_converted'] ?? $data['price'] ?? 0;
        $data['status'] = ExpenseService::getTourStatus($allExpenses);
        $data['expenses_total'] = $totalExpenses;
        $data['income'] = $price - $totalExpenses;

        ExpenseService::updateTourRoomTypes($this->record->id, $data);

//        TourService::sendMails($formState, $formState['days'] ?? []);

        return $data;
    }

    protected function afterSave(): void
    {
        TourService::sendTelegram($this->form->getRawState());
    }

    protected function getHeaderActions(): array
    {
        /** @var Tour $tour */
        $tour = $this->record;
        return [
            /*SendMailAction::make('mail_rest')
                ->tour($tour)
                ->type('restaurants')
                ->label('Mail Restaurants'),*/
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
            /*
            Actions\Action::make('export_hotel')
                ->label('Hotels')
                ->icon('heroicon-o-document-text')
                ->url(route('export-hotel', $this->record)),
            Actions\Action::make('export_museum')
                ->label('Museums')
                ->icon('heroicon-o-document-text')
                ->url(route('export-museum', $this->record)),
            Actions\Action::make('export_client')
                ->label('Client')
                ->icon('heroicon-o-document-text')
                ->url(route('export-client', $this->record)),
            Actions\Action::make('export')
                ->label('Report')
                ->icon('heroicon-o-document-text')
                ->url(route('export', $this->record)),*/
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

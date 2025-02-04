<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Filament\Resources\TourCorporateResource;
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
        $allExpenses = ExpenseService::mutateExpenses($formState, isCorporate: true);

        $data['status'] = ExpenseService::getTourStatus($allExpenses);
        $data['expenses_total'] = $allExpenses->sum('price');

        ExpenseService::updateTourRoomTypes($this->record->id, $data);

        TourService::sendMails($formState, $allExpenses, isCorporate: true);

        TourService::sendTelegram($formState, isCorporate: true);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
//            Actions\Action::make('export_hotel')
//                ->label('Hotels')
//                ->icon('heroicon-o-document-text')
//                ->url(route('export-hotel', $this->record)),
//            Actions\Action::make('export_museum')
//                ->label('Museums')
//                ->icon('heroicon-o-document-text')
//                ->url(route('export-museum', $this->record)),
//            Actions\Action::make('export_client')
//                ->label('Client')
//                ->icon('heroicon-o-document-text')
//                ->url(route('export-client', $this->record)),
//            Actions\Action::make('export')
//                ->label('Report')
//                ->icon('heroicon-o-document-text')
//                ->url(route('export', $this->record)),
            Actions\DeleteAction::make()
                ->label('Delete')
                ->icon('heroicon-o-trash'),
        ];
    }
}

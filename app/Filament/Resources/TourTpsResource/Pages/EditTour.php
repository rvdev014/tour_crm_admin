<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\ExpenseStatus;
use App\Enums\TourStatus;
use App\Filament\Resources\TourTpsResource;
use App\Models\TourRoomType;
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
        $totalExpenses = $allExpenses->sum('price') + ($data['guide_price'] ?? 0);

        $data['status'] = ExpenseService::getTourStatus($allExpenses);
        $data['expenses_total'] = $totalExpenses;
        $data['income'] = $data['price'] - $totalExpenses;

        ExpenseService::updateTourRoomTypes($this->record->id, $data);

        TourService::sendMails($formState, $formState['days'] ?? []);
        TourService::sendTelegram($formState);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
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
                ->url(route('export', $this->record)),
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

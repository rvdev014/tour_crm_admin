<?php

namespace App\Filament\Resources\TourTpsTestResource\Pages;

use App\Filament\Resources\TourTpsResource;
use App\Filament\Resources\TourTpsResource\Actions\SendMailAction;
use App\Filament\Resources\TourTpsTestResource;
use App\Models\Tour;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourTpsTestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        ExpenseService::updateTourRoomTypes($this->record->id, $data);

        ExpenseService::convertExpensePrice($data, 'price');
        ExpenseService::convertExpensePrice($data, 'guide_price');
        ExpenseService::convertExpensePrice($data, 'transport_price');

        $data['price_result'] = $data['price_converted'] ?? $data['price'] ?? 0;
        $data['guide_price_result'] = $data['guide_price_converted'] ?? $data['guide_price'] ?? 0;
        $data['transport_price_result'] = $data['transport_price_converted'] ?? $data['transport_price'] ?? 0;

        $expensesTotal = ExpenseService::updateExpensesPricesTps($this->record, $data, true);
        $data['expenses_total'] = $expensesTotal + $data['guide_price_result'];
        $data['income'] = $data['price_result'] - $data['expenses_total'];

        return $data;
    }

    protected function afterSave(): void
    {
        TourService::sendTelegram($this->form->getRawState(), isUpdated: true);
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
            Actions\Action::make('export')
                ->label('Report')
                ->icon('heroicon-o-document-text')
                ->url(route('export', $this->record)),
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

    /*protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }*/
}

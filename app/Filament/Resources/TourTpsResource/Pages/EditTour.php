<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Filament\Resources\TourTpsResource;
use App\Filament\Resources\TourTpsResource\Actions\SendMailAction;
use App\Models\Tour;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EditTour extends EditRecord
{
    protected static string $resource = TourTpsResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        ExpenseService::updateTourRoomTypes($this->record->id, $data);

        ExpenseService::convertExpensePrice($data, 'price');
        ExpenseService::convertExpensePrice($data, 'guide_price');
        ExpenseService::convertExpensePrice($data, 'transport_price');

        $totalPax = $data['pax'] + ($data['leader_pax'] ?? 0);

        $data['price_result'] = $data['price_converted'] ?? $data['price'] ?? 0;
        $data['total_price'] = round($data['price_result'] * $totalPax, 2);

        $data['guide_price_result'] = $data['guide_price_converted'] ?? $data['guide_price'] ?? 0;
        $data['transport_price_result'] = $data['transport_price_converted'] ?? $data['transport_price'] ?? 0;

        $expensesTotal = ExpenseService::updateExpensesPricesTps($this->record, $data, true);
        $data['expenses_total'] = $expensesTotal + $data['guide_price_result'];
        $data['income'] = $data['total_price'] - $data['expenses_total'];

        return $data;
    }

    protected function afterSave(): void
    {
        TourService::sendTelegram($this->form->getRawState(), isUpdated: true);
    }

    public function getTitle(): string | Htmlable
    {
        $title = parent::getTitle();

        if ($this->record->is_cancelled) {
            return new HtmlString(
                $title . ' <span style="color: #ef4444; font-weight: bold; margin-left: 10px;">[CANCELLED!]</span>'
            );
        }

        return $title;
    }

    public function getSubheading(): string | Htmlable | null
    {
        if ($this->record->is_cancelled) {
            return new HtmlString('<div class="text-danger-600 font-bold uppercase underline">This tour has been officially cancelled and is no longer active.</div>');
        }

        return null;
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
//            Actions\Action::make('export')
//                ->label('Report Client')
//                ->icon('heroicon-o-document-text')
//                ->url(route('export-client', $this->record)),
//            Actions\Action::make('export')
//                ->label('Report')
//                ->icon('heroicon-o-document-text')
//                ->url(route('export', $this->record)),
            Actions\Action::make('cancelTour')
                ->label('Cancel')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                // Add a confirmation modal so it's not clicked by accident
                ->requiresConfirmation()
                ->modalHeading('Cancel Tour')
                ->modalDescription('Are you sure you want to cancel this tour? This will mark it as cancelled in the system.')
                ->modalSubmitActionLabel('Yes, cancel it')
                ->action(function () {
                    // This logic runs when the button is clicked
                    $this->record->update([
                        'is_cancelled' => true,
                    ]);

                    Notification::make()
                        ->title('Tour Cancelled')
                        ->success()
                        ->send();
                })
                // Hide the button if the tour is already cancelled
                ->hidden(fn () => $this->record->is_cancelled),
            SendMailAction::make('mail_hotel')
                ->tour($tour)
                ->type('hotels')
                ->label('Mail Hotels'),
            Actions\Action::make('export_all')
                ->label('Export All')
                ->icon('heroicon-o-document-text')
                ->url(route('export-all', $this->record)),
//            Actions\DeleteAction::make()
//                ->label('Delete')
//                ->icon('heroicon-o-trash'),
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

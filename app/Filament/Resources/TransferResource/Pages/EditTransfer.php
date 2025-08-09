<?php

namespace App\Filament\Resources\TransferResource\Pages;

use App\Enums\ExpenseStatus;
use App\Enums\TourStatus;
use App\Filament\Resources\TransferResource;
use App\Models\Transfer;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransfer extends EditRecord
{
    protected static string $resource = TransferResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        ExpenseService::convertExpensePrice($data, 'sell_price');
        ExpenseService::convertExpensePrice($data, 'buy_price');

        $data['sell_price_result'] = $data['sell_price_converted'] ?? $data['sell_price'] ?? 0;
        $data['buy_price_result'] = $data['buy_price_converted'] ?? $data['buy_price'] ?? 0;

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Transfer $transfer */
        $transfer = $this->record;

        $transfer->tourDayExpense?->updateQuietly([
            'to_city_id' => $transfer->to_city_id,
            'comment' => $transfer->comment,
            'transport_type' => $transfer->transport_type,
            'price' => $transfer->price,
            'status' => $transfer->status,
            'nameplate' => $transfer->nameplate,
            'transport_driver_ids' => $transfer->driver_ids,
            'transport_place' => $transfer->place_of_submission,
            'transport_route' => $transfer->route,
        ]);

        if ($transfer->status == ExpenseStatus::Confirmed) {
            TourService::sendTelegramTransfer($transfer->toArray(), true);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_all')
                ->label('Export voucher')
                ->icon('heroicon-o-document-text')
                ->visible($this->record->status == ExpenseStatus::Confirmed)
                ->url(route('export-transfer', $this->record)),
            Actions\DeleteAction::make(),
        ];
    }
}

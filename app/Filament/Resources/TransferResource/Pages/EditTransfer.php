<?php

namespace App\Filament\Resources\TransferResource\Pages;

use App\Enums\ExpenseStatus;
use App\Enums\TourStatus;
use App\Filament\Resources\TransferResource;
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

    protected function afterSave(): void
    {
        if ($this->record->status == ExpenseStatus::Confirmed) {
            TourService::sendTelegramTransfer($this->record->toArray(), true);
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

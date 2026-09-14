<?php

namespace App\Filament\Resources\TransferExtraResource\Pages;

use App\Filament\Resources\TransferExtraResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTransferExtras extends ManageRecords
{
    protected static string $resource = TransferExtraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

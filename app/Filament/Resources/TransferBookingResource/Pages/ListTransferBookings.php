<?php

namespace App\Filament\Resources\TransferBookingResource\Pages;

use App\Filament\Resources\TransferBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListTransferBookings extends ListRecords
{
    protected static string $resource = TransferBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransferRequestStatus: int implements HasLabel, HasColor
{
    case Created = 1;
    case TransportType = 2;
    case Booked = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::TransportType => 'Transport Type',
            self::Booked => 'Booked',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Created => 'gray',
            self::TransportType => 'warning',
            self::Booked => 'success',
        };
    }
}
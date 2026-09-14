<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TransferLegDirection: string implements HasLabel
{
    case Departure = 'departure';
    case Return = 'return';

    public function getLabel(): string
    {
        return match ($this) {
            self::Departure => 'Departure',
            self::Return => 'Return',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransportType: int implements HasLabel, HasColor
{
    case Sedan = 1;
    case SUV = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Sedan => 'Sedan',
            self::SUV => 'SUV',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sedan => '',
            self::SUV => '',
        };
    }
}

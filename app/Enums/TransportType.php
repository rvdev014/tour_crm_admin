<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransportType: int implements HasLabel, HasColor
{
    case Sedan = 1;
    case SUV = 2;
    case Minivan = 3;
    case Minibus = 4;
    case Bus30 = 5;
    case Bus40 = 6;

    public function getLabel(): string
    {
        return match ($this) {
            self::Sedan => 'Sedan',
            self::SUV => 'SUV',
            self::Minivan => 'Minivan 3-4',
            self::Minibus => 'Minibus 5-14',
            self::Bus30 => 'Bus 30-35',
            self::Bus40 => 'Bus 40-45',
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

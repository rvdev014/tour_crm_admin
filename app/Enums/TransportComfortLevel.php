<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransportComfortLevel:int implements HasLabel, HasColor
{
    case Standard = 1;
    case Comfort = 2;
    case Lux = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Comfort  => 'Comfort',
            self::Lux      => 'Lux',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Standard => '',
            self::Comfort  => '',
            self::Lux      => '',
        };
    }
}

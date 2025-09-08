<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransportClassEnum: int implements HasLabel, HasColor
{
    case Economy = 1;
    case Business = 2;
    case FirstClass = 3;
    case Premium = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::Economy => 'Economy',
            self::Business => 'Business',
            self::FirstClass => 'First Class',
            self::Premium => 'Premium',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Economy => 'gray',
            self::Business => 'blue',
            self::FirstClass => 'success',
            self::Premium => 'warning',
        };
    }
}
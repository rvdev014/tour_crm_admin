<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TrainClass: int implements HasLabel, HasColor
{
    case SecondClass = 1;
    case BusinessClass = 2;
    case VIP = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::SecondClass => 'Second Class',
            self::BusinessClass => 'Business Class',
            self::VIP => 'VIP',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SecondClass => 'bg-blue-500',
            self::BusinessClass => 'bg-green-500',
            self::VIP => 'bg-red-500',
        };
    }
}

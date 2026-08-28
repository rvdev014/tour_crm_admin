<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WagonClass: string implements HasColor, HasLabel
{
    case Seated = 'seated';
    case Platskart = 'platskart';
    case Kupe = 'kupe';
    case Sv = 'sv';

    public function getLabel(): string
    {
        return match ($this) {
            self::Seated => 'Seated',
            self::Platskart => 'Platskart',
            self::Kupe => 'Kupe',
            self::Sv => 'SV / Lux',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Seated => 'gray',
            self::Platskart => 'info',
            self::Kupe => 'warning',
            self::Sv => 'success',
        };
    }
}

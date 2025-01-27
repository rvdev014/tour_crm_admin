<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExpenseStatus:int implements HasLabel, HasColor
{
    case New = 1;
    case Confirmed = 2;
    case Waiting = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::New       => 'New',
            self::Confirmed => 'Confirmed',
            self::Waiting   => 'Waiting',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::New       => 'primary',
            self::Confirmed => 'success',
            self::Waiting   => 'warning',
        };
    }
}

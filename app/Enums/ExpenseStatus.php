<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExpenseStatus: int implements HasLabel, HasColor
{
    case Confirmed = 1;
    case Waiting = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Waiting => 'Waiting',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Waiting => 'yellow',
        };
    }
}

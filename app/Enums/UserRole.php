<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole:int implements HasLabel, HasColor
{
    case Admin = 0;
    case Operator = 1;
    case Accountant = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin    => 'Admin',
            self::Operator => 'Operator',
            self::Accountant => 'Accountant',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Admin    => 'red',
            self::Operator => 'green',
            self::Accountant => 'blue',
        };
    }
}

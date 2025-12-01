<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentType: int implements HasLabel, HasIcon, HasColor
{
    case Cash = 1;
    case Bank = 2;


    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Cash => 'success',
            self::Bank => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Cash => 'heroicon-o-cash',
            self::Bank => 'heroicon-o-bank',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank',
        };
    }
}

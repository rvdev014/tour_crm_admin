<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: int implements HasLabel, HasIcon, HasColor
{
    case Paid = 1;
    case Waiting = 2;


    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Paid => 'success',
            self::Waiting => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Paid => 'heroicon-o-cash',
            self::Waiting => 'heroicon-o-bank',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Waiting => 'Waiting',
        };
    }
}

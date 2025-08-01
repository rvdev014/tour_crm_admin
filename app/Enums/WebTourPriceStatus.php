<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum WebTourPriceStatus: int implements HasLabel, HasIcon, HasColor
{
    case Available = 1;
    case SoldOut = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::SoldOut => 'Sold Out',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Available => 'success',
            self::SoldOut => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Available => 'heroicon-o-check-circle',
            self::SoldOut => 'heroicon-o-x-circle',
        };
    }
}

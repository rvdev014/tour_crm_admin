<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TourStatus:int implements HasLabel, HasColor, HasIcon
{
    case Confirmed = 1;
    case NotConfirmed = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Confirmed    => 'Confirmed',
            self::NotConfirmed => 'Not Confirmed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Confirmed    => 'success',
            self::NotConfirmed => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Confirmed    => '',
            self::NotConfirmed => '',
        };
    }
}

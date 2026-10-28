<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TourStatus:int implements HasLabel, HasColor, HasIcon
{
    case Confirmed = 1;
    case NotConfirmed = 2;
    // Display-only: derived from tours.is_cancelled (see Tour::displayStatus()), never stored in tours.status.
    case Cancelled = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Confirmed    => 'Confirmed',
            self::NotConfirmed => 'Not Confirmed',
            self::Cancelled    => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Confirmed    => 'success',
            self::NotConfirmed => 'danger',
            self::Cancelled    => 'gray',
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

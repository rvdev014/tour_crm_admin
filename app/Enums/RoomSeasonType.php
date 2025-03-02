<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RoomSeasonType: int implements HasLabel, HasColor, HasIcon
{
    case OffSeasonal = 1;
    case Seasonal = 2;
    case Standard = 3;

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OffSeasonal => 'gray',
            self::Seasonal => 'info',
            self::Standard => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OffSeasonal => 'heroicon-o-sun',
            self::Seasonal => 'heroicon-o-sun',
            self::Standard => 'heroicon-o-sun',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OffSeasonal => 'Off Seasonal',
            self::Seasonal => 'Seasonal',
            self::Standard => 'Standard',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RoomSeasonType: int implements HasLabel, HasColor, HasIcon
{
    case Season1 = 1;
    case Season2 = 2;
    case Season3 = 3;
    case Season4 = 4;

    public static function getValues(): array
    {
        return [
            self::Season1,
            self::Season2,
            self::Season3,
            self::Season4,
        ];
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Season1 => 'gray',
            self::Season2 => 'info',
            self::Season3 => 'success',
            self::Season4 => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Season1 => 'heroicon-o-sun',
            self::Season2 => 'heroicon-o-sun',
            self::Season3 => 'heroicon-o-sun',
            self::Season4 => 'heroicon-o-sun',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Season1 => 'Season 1',
            self::Season2 => 'Season 2',
            self::Season3 => 'Season 3',
            self::Season4 => 'Season 4',
        };
    }
}

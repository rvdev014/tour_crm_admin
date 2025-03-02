<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RoomPersonType: int implements HasLabel, HasColor, HasIcon
{
    case Uzbek = 1;
    case Foreign = 2;

    public static function getValues(): array
    {
        return [
            self::Uzbek,
            self::Foreign,
        ];
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Uzbek => 'success',
            self::Foreign => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Uzbek => 'heroicon-o-sun',
            self::Foreign => 'heroicon-o-sun',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Uzbek => 'Uzbek',
            self::Foreign => 'Foreign',
        };
    }
}

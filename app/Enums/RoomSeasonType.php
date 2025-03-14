<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RoomSeasonType: int implements HasLabel, HasColor, HasIcon
{
    case Low = 1;
    case Mid = 2;
    case High = 3;
    case Yearly = 4;

    public static function getValues(): array
    {
        return [
            self::Low,
            self::Mid,
            self::High,
            self::Yearly,
        ];
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Low => 'gray',
            self::Mid => 'info',
            self::High => 'success',
            self::Yearly => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Low => 'heroicon-o-sun',
            self::Mid => 'heroicon-o-sun',
            self::High => 'heroicon-o-sun',
            self::Yearly => 'heroicon-o-sun',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Low => 'Low season',
            self::Mid => 'Mid season',
            self::High => 'High season',
            self::Yearly => now()->year,
        };
    }
}

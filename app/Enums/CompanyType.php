<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CompanyType: int implements HasLabel, HasIcon, HasColor
{
    case TPS = 1;
    case Corporate = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::TPS => 'Guide',
            self::Corporate => 'Driver',
        };
    }

    public function getCssClass(): string
    {
        return match ($this) {
            self::TPS => 'badge badge-primary',
            self::Corporate => 'badge badge-secondary',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TPS => 'primary',
            self::Corporate => 'secondary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::TPS => '',
            self::Corporate => '',
        };
    }
}

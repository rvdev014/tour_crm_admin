<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CompanyType:int implements HasLabel, HasIcon, HasColor
{
    case TPS = 1;
    case Corporate = 2;
    case Private = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::TPS       => 'TPS',
            self::Corporate => 'Corporate',
            self::Private   => 'Private',
        };
    }

    public function getCssClass(): string
    {
        return match ($this) {
            self::TPS, self::Corporate, self::Private => '',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TPS, self::Corporate, self::Private => '',
        };
    }

    public function getIcon(): ?string
    {
        return null;
    }
}

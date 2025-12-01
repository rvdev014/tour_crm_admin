<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: int implements HasLabel, HasIcon, HasColor
{
    case Waiting = 1;
    case Done = 2;


    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Waiting => 'primary',
            self::Done => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Waiting => 'heroicon-o-clock',
            self::Done => 'heroicon-o-check',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Done => 'Done',
        };
    }
}

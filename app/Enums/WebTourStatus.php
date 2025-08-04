<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Collection;

enum WebTourStatus: int implements HasLabel, HasIcon, HasColor
{
    case New = 1;
    case Waiting = 2;
    case Done = 3;
    case Rejected = 4;

    public static function getValues(): array
    {
        return collect(self::cases())->map(fn ($case) => $case->value)->toArray();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Waiting => 'Waiting',
            self::Done => 'Done',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::New => 'gray',
            self::Waiting => 'warning',
            self::Done => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::New => '',
            self::Waiting => '',
            self::Done => '',
            self::Rejected => '',
        };
    }
}

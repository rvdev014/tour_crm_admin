<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ExpenseStatus:int implements HasLabel, HasColor, HasIcon
{
    case New = 1;
    case Confirmed = 2;
//    case Waiting = 3;
    case Rejected = 4;
    case Done = 5;

    public function getLabel(): string
    {
        return match ($this) {
            self::New       => 'New',
            self::Confirmed => 'Confirmed',
//            self::Waiting   => 'Waiting',
            self::Rejected  => 'Rejected',
            self::Done      => 'Done',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::New       => 'info',
            self::Confirmed => 'gray',
//            self::Waiting   => 'warning',
            self::Rejected  => 'danger',
            self::Done      => 'success',
        };
    }

    // These statuses are shown as color-only badges packed densely into the
    // day/expense report tables (status_view.blade.php and friends) — an icon
    // per status means the state doesn't rely on color alone to read (teal
    // primary buttons and green success badges sit close in hue elsewhere in
    // the app, and colorblind users need a second cue anyway).
    public function getIcon(): ?string
    {
        return match ($this) {
            self::New       => 'heroicon-o-clock',
            self::Confirmed => 'heroicon-o-check',
//            self::Waiting   => 'heroicon-o-ellipsis-horizontal',
            self::Rejected  => 'heroicon-o-x-circle',
            self::Done      => 'heroicon-o-check-circle',
        };
    }
}

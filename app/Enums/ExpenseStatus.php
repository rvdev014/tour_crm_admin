<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExpenseStatus:int implements HasLabel, HasColor
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
}

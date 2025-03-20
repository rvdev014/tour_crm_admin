<?php

namespace App\Enums;

enum CurrencyEnum:string
{
    case UZS = 'UZS';
    case USD = 'USD';

    public function getLabel(): string
    {
        return match ($this) {
            self::UZS => 'UZS',
            self::USD => 'USD',
        };
    }
}

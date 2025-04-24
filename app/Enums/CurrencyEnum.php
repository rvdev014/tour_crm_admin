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

    public function getSymbol(): string
    {
        return match ($this) {
            self::UZS => 'sum',
            self::USD => '$',
        };
    }
}

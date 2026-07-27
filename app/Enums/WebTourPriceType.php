<?php

namespace App\Enums;

enum WebTourPriceType: string
{
    case Default = 'default';
    case Free = 'free';
    case PerPerson = 'per_person';

    public function getLabel(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Free => 'Free',
            self::PerPerson => 'Per Person',
        };
    }
}

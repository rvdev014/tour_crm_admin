<?php

namespace App\Enums;

enum WebTourType: int
{
    case Small = 1;
    case Private = 2;
    case Custom = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Small => 'Small',
            self::Private => 'Private',
            self::Custom => 'Custom',
        };
    }
}

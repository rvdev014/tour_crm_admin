<?php

namespace App\Enums;

enum PlaneType:int
{
    case International = 1;
    case Domestic = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::International => 'International',
            self::Domestic => 'Domestic',
        };
    }
}

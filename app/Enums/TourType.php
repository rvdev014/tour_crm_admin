<?php

namespace App\Enums;

enum TourType: int
{
    case TPS = 1;
    case Corporate = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::TPS => 'TPS',
            self::Corporate => 'Corporate',
        };
    }
}

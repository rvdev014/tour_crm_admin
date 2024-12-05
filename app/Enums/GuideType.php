<?php

namespace App\Enums;

enum GuideType: int
{
    case Local = 1;
    case Private = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Local => 'Local',
            self::Private => 'Private',
        };
    }
}
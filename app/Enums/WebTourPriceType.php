<?php

namespace App\Enums;

enum WebTourPriceType: string
{
    case Default = 'default';
    case Free = 'free';
    
    public function getLabel(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Free => 'Free',
        };
    }
}
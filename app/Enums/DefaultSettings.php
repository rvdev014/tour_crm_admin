<?php

namespace App\Enums;

enum DefaultSettings: string
{
    case TOUR_SBOR = 'tour_sbor';

    public function getLabel(): string
    {
        return match ($this) {
            self::TOUR_SBOR => 'Тур сбор',
        };
    }
}

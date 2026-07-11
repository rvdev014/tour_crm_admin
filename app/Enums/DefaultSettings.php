<?php

namespace App\Enums;

enum DefaultSettings: string
{
    case TOUR_SBOR = 'tour_sbor';
    case VAT_PERCENT = 'vat_percent';

    public function getLabel(): string
    {
        return match ($this) {
            self::TOUR_SBOR => 'Тур сбор',
            self::VAT_PERCENT => 'НДС (%)',
        };
    }
}

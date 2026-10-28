<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

// Seated-train classes sold as website "guaranteed tickets". Distinct from TrainClass,
// which prices tour expenses.
enum TrainWebClass: string implements HasLabel
{
    case Economy = 'economy';
    case Business = 'business';
    case Vip = 'vip';

    public function getLabel(): string
    {
        return match ($this) {
            self::Economy => 'Economy',
            self::Business => 'Business',
            self::Vip => 'VIP',
        };
    }

    public function priceColumn(): string
    {
        return 'price_'.$this->value;
    }
}

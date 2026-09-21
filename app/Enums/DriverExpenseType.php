<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a driver spent money on during a trip. The order is the order shown in the cabinet's picker.
 */
enum DriverExpenseType: string implements HasLabel
{
    case DriverServices = 'driver_services';
    case Parking = 'parking';
    case Fuel = 'fuel';
    case Road = 'road';
    case Wash = 'wash';
    case Water = 'water';
    case Other = 'other';

    public function getLabel(): string
    {
        return __('driver.expenses.types.'.$this->value);
    }
}

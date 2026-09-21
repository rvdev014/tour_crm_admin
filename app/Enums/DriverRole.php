<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a driver-cabinet account is allowed to do.
 *
 * Driver     — sees and advances only their own transfers; can be assigned to trips.
 * Dispatcher — sees every transfer and every driver's contact details and can set any status, but is
 *              never offered as a driver on a trip (TourService::getDrivers() excludes them).
 */
enum DriverRole: string implements HasColor, HasLabel
{
    case Driver = 'driver';
    case Dispatcher = 'dispatcher';

    public function getLabel(): string
    {
        return match ($this) {
            self::Driver => __('Driver'),
            self::Dispatcher => __('Dispatcher'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Driver => 'gray',
            self::Dispatcher => 'warning',
        };
    }
}

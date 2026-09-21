<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * An operator's review of a driver-entered expense. Purely a record of "somebody checked this": it does
 * not change any transfer or tour financial total.
 */
enum DriverExpenseStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('driver.expenses.statuses.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::New => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}

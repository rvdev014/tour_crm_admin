<?php

namespace App\Enums;

enum EmployeeType:int
{
    case Guide = 1;
    case Driver = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Guide  => 'Guide',
            self::Driver => 'Driver',
        };
    }

    public function getCssClass(): string
    {
        return match ($this) {
            self::Guide  => 'badge badge-primary',
            self::Driver => 'badge badge-secondary',
        };
    }
}

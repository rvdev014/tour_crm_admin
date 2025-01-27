<?php

namespace App\Enums;

enum TransportStatus:int
{
    case Active = 1;
    case Pending = 2;
    case Completed = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Active    => 'Active',
            self::Pending   => 'Pending',
            self::Completed => 'Completed',
        };
    }

    public function getCssClass(): string
    {
        return match ($this) {
            self::Active    => 'bg-blue-500',
            self::Pending   => 'bg-yellow-500',
            self::Completed => 'bg-green-500',
        };
    }
}

<?php

namespace App\Enums;

enum TicketType: int
{
    case Bus = 1;
    case Train = 2;
    case Plain = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Bus => 'Bus',
            self::Train => 'Train',
            self::Plain => 'Plain',
        };
    }
}

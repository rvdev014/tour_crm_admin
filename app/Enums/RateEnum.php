<?php

namespace App\Enums;

enum RateEnum:int
{
    case One = 1;
    case Two = 2;
    case Three = 3;
    case Four = 4;
    case Five = 5;
    case Boutique = 6;

    public function getLabel(): string
    {
        return match ($this) {
            self::One   => '⭐️',
            self::Two   => '⭐️⭐️',
            self::Three => '⭐️⭐️⭐️',
            self::Four  => '⭐️⭐️⭐️⭐️',
            self::Five  => '⭐️⭐️⭐️⭐️⭐️',
            self::Boutique => 'Boutique',
        };
    }
}

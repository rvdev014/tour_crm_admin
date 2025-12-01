<?php

namespace App\Enums;

enum RoomType:int
{
    case Single = 1;
    case Double = 2;
    case Triple = 3;
    case Quad = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Double => 'Double',
            self::Triple => 'Triple',
            self::Quad   => 'Quad',
        };
    }
}

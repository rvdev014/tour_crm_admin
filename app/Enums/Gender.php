<?php

namespace App\Enums;

enum Gender: int
{
    case MAN = 1;
    case WOMAN = 2;

    public static function values(): array
    {
        return [
            self::MAN,
            self::WOMAN,
        ];
    }


    public function getLabel(): string
    {
        return match ($this) {
            self::MAN => 'Man',
            self::WOMAN => 'Woman',
        };
    }
}

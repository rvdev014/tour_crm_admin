<?php

namespace App\Enums;

enum Lang: string
{
    case EN = 'en';
    case RU = 'ru';
    case UZ = 'uz';

    public static function values(): array
    {
        return [
            self::EN->value,
            self::RU->value,
            self::UZ->value,
        ];
    }

    public static function valuesStr(): string
    {
        return implode(',', self::values());
    }

    public static function default(): Lang
    {
        return self::EN;
    }

    public static function fromValue($getLocale)
    {
        return match ($getLocale) {
            'en' => self::EN,
            'ru' => self::RU,
            'uz' => self::UZ,
            default => self::default(),
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::EN => 'English',
            self::RU => 'Русский',
            self::UZ => "O'zbekcha",
        };
    }

    public function getLocale(): string
    {
        return match ($this) {
            self::EN => 'en',
            self::RU => 'ru',
            self::UZ => 'uz',
        };
    }

}

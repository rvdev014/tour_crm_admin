<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Date labels for the driver cabinet.
 *
 * Carbon's plain `uz` locale is CYRILLIC ("душанба, 21 сентябр"); the cabinet's Uzbek is Latin, which
 * is Carbon's `uz_Latn`. Every date shown to a driver goes through here so that cannot be forgotten.
 */
final class DriverDates
{
    public static function locale(): string
    {
        $locale = app()->getLocale();

        return $locale === 'uz' ? 'uz_Latn' : $locale;
    }

    /** "21 сентября, понедельник" */
    public static function long(CarbonInterface $date): string
    {
        return $date->copy()->locale(self::locale())->translatedFormat('j F, l');
    }

    /** "пн" */
    public static function weekday(CarbonInterface $date): string
    {
        return $date->copy()->locale(self::locale())->isoFormat('dd');
    }

    /** "21 сентября" */
    public static function short(CarbonInterface $date): string
    {
        return $date->copy()->locale(self::locale())->translatedFormat('j F');
    }
}

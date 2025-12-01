<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    public static array $cache = [];

    public static function remember($key, callable $cb): mixed
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        self::$cache[$key] = $cb();

        return self::$cache[$key];
    }
}

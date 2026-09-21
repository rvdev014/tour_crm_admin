<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Turns a "quick choice" (today, this month, ...) into dates. "Today" is Tashkent's today: transfer times are
 * stored as local naive times, and the owner's day is not UTC's.
 */
final class AnalyticsPeriod
{
    public const PRESETS = ['today', 'yesterday', 'last7', 'this_month', 'last_month', 'this_quarter', 'this_year'];

    public const DEFAULT = 'this_month';

    /**
     * @return array{0: Carbon, 1: Carbon}|null null for an unknown preset (including "custom")
     */
    public static function dates(string $preset, ?Carbon $now = null): ?array
    {
        $now = ($now ?? Carbon::now('Asia/Tashkent'))->copy()->startOfDay();

        return match ($preset) {
            'today' => [$now->copy(), $now->copy()],
            'yesterday' => [$now->copy()->subDay(), $now->copy()->subDay()],
            'last7' => [$now->copy()->subDays(6), $now->copy()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()->startOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()->startOfDay()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()->startOfDay()],
            default => null,
        };
    }
}

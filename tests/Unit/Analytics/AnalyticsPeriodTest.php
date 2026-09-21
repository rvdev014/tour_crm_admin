<?php

namespace Tests\Unit\Analytics;

use App\Support\AnalyticsPeriod as P;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class AnalyticsPeriodTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function presets(): array
    {
        // "now" is Wednesday 2026-05-20
        return [
            'today' => ['today', '2026-05-20', '2026-05-20'],
            'yesterday' => ['yesterday', '2026-05-19', '2026-05-19'],
            'last 7 days include today' => ['last7', '2026-05-14', '2026-05-20'],
            'this month' => ['this_month', '2026-05-01', '2026-05-31'],
            'last month' => ['last_month', '2026-04-01', '2026-04-30'],
            'this quarter' => ['this_quarter', '2026-04-01', '2026-06-30'],
            'this year' => ['this_year', '2026-01-01', '2026-12-31'],
        ];
    }

    /** @dataProvider presets */
    public function test_each_preset_gives_the_expected_dates(string $preset, string $from, string $to): void
    {
        [$a, $b] = P::dates($preset, Carbon::parse('2026-05-20 15:30'));

        $this->assertSame($from, $a->toDateString());
        $this->assertSame($to, $b->toDateString());
    }

    public function test_last_month_in_march_is_february_not_an_overflowed_date(): void
    {
        [$a, $b] = P::dates('last_month', Carbon::parse('2026-03-31'));

        $this->assertSame(['2026-02-01', '2026-02-28'], [$a->toDateString(), $b->toDateString()]);
    }

    public function test_last_month_in_january_is_december_of_the_previous_year(): void
    {
        [$a, $b] = P::dates('last_month', Carbon::parse('2026-01-15'));

        $this->assertSame(['2025-12-01', '2025-12-31'], [$a->toDateString(), $b->toDateString()]);
    }

    public function test_an_unknown_preset_and_custom_give_null(): void
    {
        $this->assertNull(P::dates('custom'));
        $this->assertNull(P::dates('nonsense'));
    }

    public function test_every_listed_preset_is_understood_and_the_default_is_one_of_them(): void
    {
        foreach (P::PRESETS as $p) {
            $this->assertNotNull(P::dates($p), $p);
        }
        $this->assertContains(P::DEFAULT, P::PRESETS);
    }

    public function test_the_callers_date_is_not_mutated(): void
    {
        $now = Carbon::parse('2026-05-20 15:30');
        P::dates('this_month', $now);

        $this->assertSame('2026-05-20 15:30:00', $now->toDateTimeString());
    }
}

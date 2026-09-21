<?php

namespace App\Support;

/**
 * Small server-rendered SVG charts for the analytics page.
 *
 * Deliberately not a chart library: the admin's CSS/JS is prebuilt and committed (production does not run a
 * build), so nothing that needs new Tailwind classes or a new script can be added to the page. Inline SVG plus
 * a scoped <style> block needs neither, works in dark mode via currentColor, and is trivially testable.
 *
 * Every string that ends up in the markup is escaped; the numbers are formatted here, not by the caller.
 */
final class AnalyticsCharts
{
    private const W = 720;

    private const H = 240;

    private const PAD_L = 8;

    private const PAD_R = 8;

    private const PAD_T = 14;

    private const PAD_B = 34;

    /**
     * Grouped bars per period: revenue, expenses and profit.
     *
     * @param  array<string, array{revenue: float, expenses: float, profit: float}>  $periods  label => values
     * @param  array{revenue: string, expenses: string, profit: string}  $legend
     */
    public static function bars(array $periods, array $legend): string
    {
        if ($periods === []) {
            return '';
        }

        $series = ['revenue' => 'ta-c-rev', 'expenses' => 'ta-c-exp', 'profit' => 'ta-c-profit'];
        $n = count($periods);
        $plotW = self::W - self::PAD_L - self::PAD_R;
        $plotH = self::H - self::PAD_T - self::PAD_B;

        $max = 0.0;
        $min = 0.0;
        foreach ($periods as $p) {
            foreach (array_keys($series) as $k) {
                $max = max($max, (float) $p[$k]);
                $min = min($min, (float) $p[$k]);
            }
        }
        $range = ($max - $min) ?: 1.0;
        $zeroY = self::PAD_T + $plotH * ($max / $range);

        $slot = $plotW / $n;
        $barW = max(2.0, min(22.0, ($slot * 0.8) / 3));
        $svg = '';

        // zero line
        $svg .= sprintf('<line class="ta-axis" x1="%d" y1="%.1f" x2="%d" y2="%.1f"/>', self::PAD_L, $zeroY, self::W - self::PAD_R, $zeroY);

        $i = 0;
        foreach ($periods as $label => $p) {
            $x0 = self::PAD_L + $slot * $i + ($slot - $barW * 3) / 2;
            foreach (array_keys($series) as $j => $k) {
                $v = (float) $p[$k];
                $h = abs($v) / $range * $plotH;
                $y = $v >= 0 ? $zeroY - $h : $zeroY;
                $class = $series[$k].($k === 'profit' && $v < 0 ? ' ta-neg' : '');
                $svg .= sprintf(
                    '<rect class="%s" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="1.5"><title>%s</title></rect>',
                    $class, $x0 + $barW * $j, $y, $barW, max($h, $v == 0.0 ? 0 : 0.5),
                    e($legend[$k]).' — '.e($label).': '.number_format($v, 0, '.', ' '),
                );
            }

            // label under the group; skip some when there are many so they do not collide
            $every = (int) ceil($n / 12);
            if ($i % $every === 0) {
                $svg .= sprintf(
                    '<text class="ta-label" x="%.1f" y="%d" text-anchor="middle">%s</text>',
                    self::PAD_L + $slot * $i + $slot / 2, self::H - 14, e($label),
                );
            }
            $i++;
        }

        return self::wrap($svg, $legend, $series);
    }

    /**
     * Two lines over consecutive days: revenue and profit.
     *
     * @param  array<string, array{revenue: float, profit: float}>  $days  label => values (in order)
     * @param  array{revenue: string, profit: string}  $legend
     */
    public static function lines(array $days, array $legend): string
    {
        if (count($days) < 2) {
            return '';
        }

        $n = count($days);
        $plotW = self::W - self::PAD_L - self::PAD_R;
        $plotH = self::H - self::PAD_T - self::PAD_B;

        $max = 0.0;
        $min = 0.0;
        foreach ($days as $d) {
            $max = max($max, (float) $d['revenue'], (float) $d['profit']);
            $min = min($min, (float) $d['revenue'], (float) $d['profit']);
        }
        $range = ($max - $min) ?: 1.0;
        $zeroY = self::PAD_T + $plotH * ($max / $range);
        $step = $plotW / ($n - 1);

        $svg = sprintf('<line class="ta-axis" x1="%d" y1="%.1f" x2="%d" y2="%.1f"/>', self::PAD_L, $zeroY, self::W - self::PAD_R, $zeroY);

        foreach (['revenue' => 'ta-c-rev', 'profit' => 'ta-c-profit'] as $k => $class) {
            $points = [];
            $i = 0;
            foreach ($days as $d) {
                $points[] = sprintf('%.1f,%.1f', self::PAD_L + $step * $i, $zeroY - (float) $d[$k] / $range * $plotH);
                $i++;
            }
            $svg .= sprintf('<polyline class="ta-line %s" points="%s"/>', $class, implode(' ', $points));
        }

        $i = 0;
        $every = (int) ceil($n / 10);
        foreach (array_keys($days) as $label) {
            if ($i % $every === 0) {
                $svg .= sprintf('<text class="ta-label" x="%.1f" y="%d" text-anchor="middle">%s</text>', self::PAD_L + $step * $i, self::H - 14, e($label));
            }
            $i++;
        }

        return self::wrap($svg, $legend, ['revenue' => 'ta-c-rev', 'profit' => 'ta-c-profit']);
    }

    /**
     * @param  array<string, string>  $legend
     * @param  array<string, string>  $series  key => css class
     */
    private static function wrap(string $inner, array $legend, array $series): string
    {
        $key = '';
        foreach ($series as $k => $class) {
            $key .= '<span class="ta-key"><i class="'.$class.'"></i>'.e($legend[$k]).'</span>';
        }

        return '<div class="ta-chart"><svg viewBox="0 0 '.self::W.' '.self::H.'" role="img" preserveAspectRatio="xMidYMid meet">'
            .$inner.'</svg><div class="ta-keys">'.$key.'</div></div>';
    }
}

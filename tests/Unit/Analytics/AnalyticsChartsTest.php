<?php

namespace Tests\Unit\Analytics;

use App\Support\AnalyticsCharts as C;
use PHPUnit\Framework\TestCase;

class AnalyticsChartsTest extends TestCase
{
    private const LEGEND3 = ['revenue' => 'Revenue', 'expenses' => 'Expenses', 'profit' => 'Profit'];

    private function svgIsWellFormed(string $html): void
    {
        $doc = new \DOMDocument;
        $this->assertTrue(@$doc->loadXML('<root>'.$html.'</root>'), 'the markup must be well-formed XML');
    }

    public function test_nothing_to_draw_gives_nothing(): void
    {
        $this->assertSame('', C::bars([], self::LEGEND3));
        $this->assertSame('', C::lines([], ['revenue' => 'R', 'profit' => 'P']));
        $this->assertSame('', C::lines(['2026-05-01' => ['revenue' => 1.0, 'profit' => 1.0]], ['revenue' => 'R', 'profit' => 'P']), 'a line needs two points');
    }

    public function test_bars_draw_three_bars_per_period_with_tooltips_and_a_legend(): void
    {
        $html = C::bars([
            'Май 2026' => ['revenue' => 1_000_000.0, 'expenses' => 400_000.0, 'profit' => 600_000.0],
            'Июн 2026' => ['revenue' => 2_000_000.0, 'expenses' => 900_000.0, 'profit' => 1_100_000.0],
        ], self::LEGEND3);

        $this->assertSame(6, substr_count($html, '<rect'));
        $this->assertStringContainsString('Revenue — Май 2026: 1 000 000', $html);
        $this->assertStringContainsString('Profit — Июн 2026: 1 100 000', $html);
        $this->assertStringContainsString('<span class="ta-key"><i class="ta-c-rev"></i>Revenue</span>', $html);
        $this->svgIsWellFormed($html);
    }

    public function test_the_tallest_bar_reaches_the_top_and_bars_scale_proportionally(): void
    {
        $html = C::bars(['A' => ['revenue' => 200.0, 'expenses' => 100.0, 'profit' => 0.0]], self::LEGEND3);
        preg_match_all('/<rect class="([^"]+)" x="[\d.]+" y="[\d.]+" width="[\d.]+" height="([\d.]+)"/', $html, $m);
        $heights = array_combine($m[1], array_map('floatval', $m[2]));

        $this->assertEqualsWithDelta($heights['ta-c-rev'] / 2, $heights['ta-c-exp'], 0.2, 'half the money, half the bar');
    }

    public function test_a_negative_profit_is_drawn_below_the_zero_line_and_marked(): void
    {
        $html = C::bars(['A' => ['revenue' => 100.0, 'expenses' => 300.0, 'profit' => -200.0]], self::LEGEND3);

        $this->assertStringContainsString('ta-c-profit ta-neg', $html);
        preg_match('/<line class="ta-axis" x1="\d+" y1="([\d.]+)"/', $html, $axis);
        preg_match('/class="ta-c-profit ta-neg" x="[\d.]+" y="([\d.]+)"/', $html, $bar);
        $this->assertEqualsWithDelta((float) $axis[1], (float) $bar[1], 0.1, 'a loss hangs from the zero line');
    }

    public function test_all_zero_data_does_not_divide_by_zero(): void
    {
        $html = C::bars(['A' => ['revenue' => 0.0, 'expenses' => 0.0, 'profit' => 0.0]], self::LEGEND3);

        $this->assertStringNotContainsString('NAN', $html);
        $this->assertStringNotContainsString('INF', $html);
        $this->svgIsWellFormed($html);
    }

    public function test_labels_are_thinned_when_there_are_many_periods(): void
    {
        $periods = [];
        foreach (range(1, 60) as $i) {
            $periods["d{$i}"] = ['revenue' => (float) $i, 'expenses' => 1.0, 'profit' => 1.0];
        }

        $this->assertLessThan(20, substr_count(C::bars($periods, self::LEGEND3), '<text'));
    }

    public function test_hostile_labels_are_escaped(): void
    {
        $html = C::bars(['<script>alert(1)</script>' => ['revenue' => 1.0, 'expenses' => 1.0, 'profit' => 1.0]], self::LEGEND3);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->svgIsWellFormed($html);
    }

    public function test_lines_draw_a_polyline_per_series_with_a_point_per_day(): void
    {
        $days = [];
        foreach (range(1, 10) as $i) {
            $days["0{$i}.05"] = ['revenue' => $i * 100.0, 'profit' => $i * 40.0];
        }

        $html = C::lines($days, ['revenue' => 'Revenue', 'profit' => 'Profit']);

        $this->assertSame(2, substr_count($html, '<polyline'));
        preg_match('/<polyline class="ta-line ta-c-rev" points="([^"]+)"/', $html, $m);
        $this->assertCount(10, explode(' ', $m[1]));
        $this->svgIsWellFormed($html);
    }
}

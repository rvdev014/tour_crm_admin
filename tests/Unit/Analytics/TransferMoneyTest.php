<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\TransferMoney as M;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransferMoneyTest extends TestCase
{
    private const RATE = 12200.0;

    // ── the three patterns measured in the real data ──────────────────────

    public function test_usd_with_a_genuinely_converted_result_uses_the_stored_historical_amount(): void
    {
        // Real row: 25 USD stored as 295 000 (a rate of 11 800, not today's 12 200).
        $r = M::normalize(25, 'USD', 295000, self::RATE);

        $this->assertSame(295000.0, $r['uzs'], 'the historical conversion is kept, not re-priced at today\'s rate');
        $this->assertSame(25.0, $r['usd']);
        $this->assertSame('USD', $r['currency']);
        $this->assertSame([], $r['flags']);
    }

    public function test_a_price_with_no_currency_and_a_small_amount_is_dollars_not_sums(): void
    {
        // Real rows: 25, 220, 300 with NO currency and result == price. The CRM's own totals count "25 sums".
        $r = M::normalize(220, null, 220, self::RATE);

        $this->assertSame('USD', $r['currency']);
        $this->assertSame(220.0, $r['usd']);
        $this->assertSame(220 * 12200.0, $r['uzs'], 'not the 220 "sums" the stored result claims');
        $this->assertContains(M::FLAG_CURRENCY_ASSUMED, $r['flags']);
        $this->assertContains(M::FLAG_RATE_CURRENT, $r['flags'], 'the stored result is the raw number, so today\'s rate is used');
    }

    public function test_a_price_with_no_currency_and_a_large_amount_is_sums(): void
    {
        // Real rows: 150 000 and up with no currency.
        $r = M::normalize(2300000, null, 2300000, self::RATE);

        $this->assertSame('UZS', $r['currency']);
        $this->assertSame(2300000.0, $r['uzs']);
        $this->assertContains(M::FLAG_CURRENCY_ASSUMED, $r['flags']);
    }

    // ── the threshold sits in the gap of the real data ────────────────────

    #[DataProvider('threshold')]
    public function test_the_assumed_currency_threshold(float $amount, string $expected): void
    {
        $this->assertSame($expected, M::normalize($amount, null, $amount, self::RATE)['currency']);
    }

    public static function threshold(): array
    {
        return [
            'largest real "small" value' => [300, 'USD'],
            'just below the threshold' => [9999.99, 'USD'],
            'at the threshold' => [10000, 'UZS'],
            'smallest real "large" value' => [150000, 'UZS'],
        ];
    }

    // ── explicit currency is believed ─────────────────────────────────────

    public function test_an_explicit_currency_is_never_second_guessed_by_magnitude(): void
    {
        // A 15 000 USD price is odd but it is what the operator said; a 50 000 UZS one is likewise UZS.
        $usd = M::normalize(15000, 'USD', 183000000, self::RATE);
        $uzs = M::normalize(50, 'UZS', 50, self::RATE);

        $this->assertSame('USD', $usd['currency']);
        $this->assertNotContains(M::FLAG_CURRENCY_ASSUMED, $usd['flags']);
        $this->assertSame('UZS', $uzs['currency']);
        $this->assertNotContains(M::FLAG_CURRENCY_ASSUMED, $uzs['flags']);
    }

    public function test_currency_is_case_and_whitespace_insensitive(): void
    {
        $this->assertSame('USD', M::normalize(10, ' usd ', 122000, self::RATE)['currency']);
        $this->assertNotContains(M::FLAG_CURRENCY_ASSUMED, M::normalize(10, ' usd ', 122000, self::RATE)['flags']);
    }

    public function test_an_unknown_currency_code_is_treated_as_not_set(): void
    {
        $r = M::normalize(50, 'EUR', 50, self::RATE);

        $this->assertSame('USD', $r['currency']);
        $this->assertContains(M::FLAG_CURRENCY_ASSUMED, $r['flags']);
    }

    // ── a stored result is trusted only when it looks converted ───────────

    #[DataProvider('unconvertedResults')]
    public function test_a_usd_price_whose_stored_result_was_never_converted_is_repriced_at_todays_rate(mixed $stored): void
    {
        $r = M::normalize(100, 'USD', $stored, self::RATE);

        $this->assertSame(100 * 12200.0, $r['uzs']);
        $this->assertContains(M::FLAG_RATE_CURRENT, $r['flags']);
    }

    public static function unconvertedResults(): array
    {
        return [
            'result equal to the price' => [100],
            'result null' => [null],
            'result zero' => [0],
            'result not a number' => ['n/a'],
            'result implausibly small' => [4000],       // 40x, not thousands
            'result implausibly large' => [9_000_000],  // 90 000x
        ];
    }

    public function test_the_stored_result_bounds_are_inclusive_at_5000_and_30000_times(): void
    {
        $this->assertSame(500000.0, M::normalize(100, 'USD', 500000, self::RATE)['uzs']);
        $this->assertSame(3000000.0, M::normalize(100, 'USD', 3000000, self::RATE)['uzs']);
    }

    // ── UZS prices ────────────────────────────────────────────────────────

    public function test_a_uzs_price_is_taken_as_entered_and_gets_a_usd_equivalent_at_todays_rate(): void
    {
        $r = M::normalize(2000000, 'UZS', 2000000, self::RATE);

        $this->assertSame(2000000.0, $r['uzs']);
        $this->assertSame(round(2000000 / 12200, 2), $r['usd']);
        $this->assertContains(M::FLAG_RATE_CURRENT, $r['flags'], 'the historical rate of a UZS price is not stored');
    }

    // ── nothing there ─────────────────────────────────────────────────────

    #[DataProvider('noPrice')]
    public function test_no_price_means_not_present_and_zero(mixed $price): void
    {
        $r = M::normalize($price, 'USD', 295000, self::RATE);

        $this->assertFalse($r['present']);
        $this->assertSame(0.0, $r['uzs']);
        $this->assertSame(0.0, $r['usd']);
        $this->assertSame([], $r['flags']);
    }

    public static function noPrice(): array
    {
        return ['null' => [null], 'zero' => [0], 'zero string' => ['0'], 'negative' => [-5], 'text' => ['abc'], 'empty' => ['']];
    }

    // ── no exchange rate configured ───────────────────────────────────────

    public function test_without_a_rate_an_unconverted_usd_price_is_flagged_not_silently_zeroed_or_guessed(): void
    {
        $r = M::normalize(100, 'USD', null, 0.0);

        $this->assertSame(100.0, $r['usd'], 'the USD amount is still known');
        $this->assertSame(0.0, $r['uzs']);
        $this->assertContains(M::FLAG_NO_RATE, $r['flags']);
    }

    public function test_without_a_rate_a_properly_converted_result_still_works(): void
    {
        $r = M::normalize(25, 'USD', 295000, 0.0);

        $this->assertSame(295000.0, $r['uzs']);
        $this->assertNotContains(M::FLAG_NO_RATE, $r['flags']);
    }

    public function test_without_a_rate_a_uzs_price_has_no_usd_equivalent_and_says_so(): void
    {
        $r = M::normalize(2000000, 'UZS', 2000000, 0.0);

        $this->assertSame(2000000.0, $r['uzs']);
        $this->assertSame(0.0, $r['usd']);
        $this->assertContains(M::FLAG_NO_RATE, $r['flags']);
    }

    public function test_uzs_to_usd_helper(): void
    {
        $this->assertSame(10.0, M::uzsToUsd(122000, 12200));
        $this->assertSame(0.0, M::uzsToUsd(122000, 0));
    }

    public function test_amounts_are_rounded_to_cents(): void
    {
        $r = M::normalize(10.005, 'USD', null, 12345.678);

        $this->assertSame(round(10.005 * 12345.678, 2), $r['uzs']);
    }
}

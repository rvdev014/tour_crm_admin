<?php

namespace App\Services\Analytics;

/**
 * Turns one stored transfer price into comparable UZS and USD amounts, and says how much of that was
 * an assumption.
 *
 * Why this exists: the transfer form stores a price, a currency and a converted `*_price_result`, but the
 * data is not uniform, and the CRM's own totals (`$t->sell_price_result ?? $t->sell_price`) add up sums
 * and dollars as if they were the same unit. Measured on real data:
 *
 *  - currency set (USD)  -> `*_result` is a genuine conversion at the rate of the day (25 -> 295 000).
 *  - currency EMPTY      -> `*_result` is just the raw number. 64 of 71 such rows are 25, 220, 300 ...: dollars
 *                           typed with the currency toggle left alone. Counted as UZS they contribute 25 sums
 *                           instead of ~300 000 — invisible in any total.
 *
 * So the rules are:
 *  1. An explicit USD/UZS currency is believed.
 *  2. No currency: below ASSUMED_USD_BELOW it is USD, otherwise UZS. The real amounts split with a huge gap
 *     (largest "small" 300, smallest "large" 150 000), so the threshold is safe — and every such row is
 *     flagged `currency_assumed` for a human to confirm.
 *  3. For USD, the stored UZS result is used only if it really looks converted (see LOOKS_CONVERTED); it
 *     carries the historical rate, which is more accurate than today's. Otherwise today's rate is used and
 *     the row is flagged `rate_current`.
 */
final class TransferMoney
{
    /** A price with no currency under this is treated as USD, at or above as UZS. */
    public const ASSUMED_USD_BELOW = 10_000;

    /** A stored UZS result counts as "really converted" when it is 5 000 – 30 000 times the USD price. */
    private const LOOKS_CONVERTED_MIN = 5_000;

    private const LOOKS_CONVERTED_MAX = 30_000;

    public const FLAG_CURRENCY_ASSUMED = 'currency_assumed';

    public const FLAG_RATE_CURRENT = 'rate_current';

    public const FLAG_NO_RATE = 'no_rate';

    /**
     * @param  mixed  $price  the price as entered (numeric, or null when none)
     * @param  string|null  $currency  'USD' / 'UZS' / anything else means "not set"
     * @param  mixed  $storedUzs  the stored `*_price_result`
     * @param  float  $usdRate  UZS per 1 USD today (0 when unknown)
     * @return array{present: bool, amount: float, currency: ?string, uzs: float, usd: float, flags: list<string>}
     */
    public static function normalize(mixed $price, ?string $currency, mixed $storedUzs, float $usdRate): array
    {
        $amount = is_numeric($price) ? (float) $price : 0.0;

        if ($amount <= 0) {
            return ['present' => false, 'amount' => 0.0, 'currency' => null, 'uzs' => 0.0, 'usd' => 0.0, 'flags' => []];
        }

        $flags = [];
        $currency = strtoupper(trim((string) $currency));

        if (! in_array($currency, ['USD', 'UZS'], true)) {
            $currency = $amount < self::ASSUMED_USD_BELOW ? 'USD' : 'UZS';
            $flags[] = self::FLAG_CURRENCY_ASSUMED;
        }

        if ($currency === 'USD') {
            $usd = $amount;
            $stored = is_numeric($storedUzs) ? (float) $storedUzs : 0.0;
            $ratio = $stored / $amount;

            if ($stored > 0 && $ratio >= self::LOOKS_CONVERTED_MIN && $ratio <= self::LOOKS_CONVERTED_MAX) {
                $uzs = $stored;   // converted when the price was entered: the historical rate
            } elseif ($usdRate > 0) {
                $uzs = $amount * $usdRate;
                $flags[] = self::FLAG_RATE_CURRENT;
            } else {
                $uzs = 0.0;
                $flags[] = self::FLAG_NO_RATE;
            }
        } else {
            $uzs = $amount;

            if ($usdRate > 0) {
                $usd = $amount / $usdRate;
                $flags[] = self::FLAG_RATE_CURRENT;   // the historical rate of a UZS price is not stored
            } else {
                $usd = 0.0;
                $flags[] = self::FLAG_NO_RATE;
            }
        }

        return [
            'present' => true,
            'amount' => round($amount, 2),
            'currency' => $currency,
            'uzs' => round($uzs, 2),
            'usd' => round($usd, 2),
            'flags' => $flags,
        ];
    }

    /** UZS -> USD at today's rate, for amounts that only exist in sums (driver expenses). 0 when there is no rate. */
    public static function uzsToUsd(float $uzs, float $usdRate): float
    {
        return $usdRate > 0 ? round($uzs / $usdRate, 2) : 0.0;
    }
}

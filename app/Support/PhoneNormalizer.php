<?php

namespace App\Support;

class PhoneNormalizer
{
    /**
     * Normalize an Uzbek phone number to "+998XXXXXXXXX", or null if it cannot be trusted.
     *
     * Accepted: 12 digits starting with 998, or a 9-digit national number. Punctuation, spaces
     * and a leading "+" are ignored.
     *
     * A 9-digit number that itself starts with "998" is rejected as ambiguous: it is far more
     * likely to be a country code with the rest truncated (e.g. "998299221") than a real national
     * number. Guessing wrong would attach a driver's login to a stranger's phone, so we return
     * null and let an operator re-enter it in full.
     */
    public static function uz(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);

        if (strlen($digits) === 12 && str_starts_with($digits, '998')) {
            return '+'.$digits;
        }

        if (strlen($digits) === 9 && ! str_starts_with($digits, '998')) {
            return '+998'.$digits;
        }

        return null;
    }

    /**
     * Value for a tel: link. Uses the normalized number when there is one; otherwise falls back to
     * whatever digits (and a leading +) the operator typed, so a call button still works for numbers
     * the strict normalizer refuses (a login must be trustworthy; a dial link only has to be dialable).
     */
    public static function tel(?string $raw): ?string
    {
        return self::uz($raw) ?? (preg_replace('/[^\d+]/', '', (string) $raw) ?: null);
    }
}

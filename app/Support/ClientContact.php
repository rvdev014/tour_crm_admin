<?php

namespace App\Support;

/**
 * Turns a client's phone number, exactly as an operator typed it or the website collected it, into the
 * links a driver can tap: call, WhatsApp, Telegram.
 *
 * The rule that matters: a messenger link is only built when we KNOW the country. wa.me and t.me need the
 * full international number, and guessing a country code for "(555) 123-4567" would open a chat with a
 * stranger. So a number without a recognisable country code gets a call link only.
 *
 *   "+44 7911 123456", "0044 7911 123456"   -> call + WhatsApp + Telegram
 *   "90 123 45 67", "998901234567"           -> Uzbek (see PhoneNormalizer::uz) -> all three
 *   "(555) 123-4567"                         -> call only
 *   "abc", "", "123"                         -> null (nothing to show)
 */
final class ClientContact
{
    /** Shortest/longest plausible phone number, in digits (E.164 allows up to 15). */
    private const MIN_DIGITS = 7;

    private const MAX_DIGITS = 15;

    /** Uzbek mobile operator codes (the two digits after +998). */
    private const UZ_MOBILE_PREFIXES = ['20', '33', '50', '55', '77', '88', '90', '91', '93', '94', '95', '97', '98', '99'];

    /**
     * @return array{display: string, tel: string, whatsapp: ?string, telegram: ?string}|null
     */
    public static function from(?string $raw): ?array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $international = self::international($raw);

        if ($international !== null) {
            $digits = ltrim($international, '+');

            return [
                'display' => $international,
                'tel' => $international,
                // wa.me wants digits only, no "+"; t.me/+<number> opens a chat by phone number.
                'whatsapp' => 'https://wa.me/'.$digits,
                'telegram' => 'https://t.me/+'.$digits,
            ];
        }

        // Country unknown: still dialable (the driver's phone applies its own local rules), but no messenger link.
        $digits = preg_replace('/\D/', '', $raw);

        if (strlen($digits) < self::MIN_DIGITS || strlen($digits) > self::MAX_DIGITS) {
            return null;
        }

        // Shown as typed, minus anything that is not part of a phone number: the value comes from a website
        // form and free-text field, so it must never carry markup or words into the page.
        $display = trim(preg_replace('/[^\d+()\-.\s]/u', '', $raw));

        return ['display' => $display, 'tel' => $digits, 'whatsapp' => null, 'telegram' => null];
    }

    /** "+<country><number>" when the country is unambiguous, else null. */
    private static function international(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);

        // A leading "+" or the international dialling prefix "00" both mean "a country code follows".
        $hasCountryCode = str_starts_with($raw, '+') || str_starts_with($digits, '00');

        if ($hasCountryCode) {
            $digits = str_starts_with($raw, '+') ? $digits : substr($digits, 2);

            return self::withinLength($digits) && $digits[0] !== '0' ? '+'.$digits : null;
        }

        // No prefix: the only country we may assume is Uzbekistan (an operator typing a local number).
        $uzbek = PhoneNormalizer::uz($raw);

        if ($uzbek === null) {
            return null;
        }

        // A full "998…" number states its country. A bare 9-digit number does not: PhoneNormalizer would call
        // ANY of them Uzbek, but a tourist typing their own 9-digit number (Spain, Germany, ...) must not be
        // turned into a WhatsApp link to a stranger's +998 number. Trust it only when it starts with a real
        // Uzbek mobile operator code.
        $digits = preg_replace('/\D/', '', $raw);

        if (strlen($digits) === 9 && ! in_array(substr($digits, 0, 2), self::UZ_MOBILE_PREFIXES, true)) {
            return null;
        }

        return $uzbek;
    }

    private static function withinLength(string $digits): bool
    {
        return $digits !== '' && strlen($digits) >= self::MIN_DIGITS && strlen($digits) <= self::MAX_DIGITS;
    }
}

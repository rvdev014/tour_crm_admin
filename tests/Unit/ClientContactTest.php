<?php

namespace Tests\Unit;

use App\Support\ClientContact;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClientContactTest extends TestCase
{
    /** @return array{display: string, tel: string, whatsapp: ?string, telegram: ?string} */
    private function all(string $e164): array
    {
        $digits = ltrim($e164, '+');

        return [
            'display' => $e164,
            'tel' => $e164,
            'whatsapp' => 'https://wa.me/'.$digits,
            'telegram' => 'https://t.me/+'.$digits,
        ];
    }

    #[DataProvider('knownCountry')]
    public function test_a_number_with_a_recognisable_country_gets_call_whatsapp_and_telegram(string $typed, string $e164): void
    {
        $this->assertSame($this->all($e164), ClientContact::from($typed));
    }

    public static function knownCountry(): array
    {
        return [
            'UK, plus and spaces' => ['+44 7911 123456', '+447911123456'],
            'UK, 00 international prefix' => ['0044 7911 123456', '+447911123456'],
            'Russia with punctuation' => ['+7 (916) 123-45-67', '+79161234567'],
            'US' => ['+1 (555) 123-4567', '+15551234567'],
            'Uzbek, full with plus' => ['+998 90 123 45 67', '+998901234567'],
            'Uzbek, full without plus' => ['998901234567', '+998901234567'],
            'Uzbek, 00 prefix' => ['00 998 90 123 45 67', '+998901234567'],
            'Uzbek local number, real mobile operator code' => ['90 123 45 67', '+998901234567'],
            'Uzbek local with dots' => ['91.123.45.67', '+998911234567'],
        ];
    }

    #[DataProvider('countryUnknown')]
    public function test_a_number_whose_country_we_cannot_know_gets_a_call_link_and_no_messenger_links(string $typed, string $tel): void
    {
        $contact = ClientContact::from($typed);

        $this->assertNotNull($contact);
        $this->assertSame($tel, $contact['tel']);
        // A wa.me / t.me link needs the country. Guessing one would open a chat with a stranger.
        $this->assertNull($contact['whatsapp']);
        $this->assertNull($contact['telegram']);
    }

    public static function countryUnknown(): array
    {
        return [
            'US-style, no country code' => ['(555) 123-4567', '5551234567'],
            // A foreign tourist typing their own 9-digit number must NOT become a +998 number.
            '9 digits, Spanish-style mobile' => ['612 345 678', '612345678'],
            '9 digits, Uzbek landline prefix is not a mobile' => ['71 234 56 78', '712345678'],
            'plus followed by a zero (not a country code)' => ['+0 123 456 789', '0123456789'],
        ];
    }

    #[DataProvider('unusable')]
    public function test_something_that_is_not_a_phone_number_gives_nothing(?string $typed): void
    {
        $this->assertNull(ClientContact::from($typed));
    }

    public static function unusable(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'whitespace' => ['   '],
            'words' => ['abc'],
            'too short' => ['12345'],
            'too short with plus' => ['+123'],
            'too long' => ['+1234567890123456'],
        ];
    }

    public function test_whatsapp_links_have_digits_only_and_telegram_links_a_single_plus(): void
    {
        $contact = ClientContact::from('+44 (7911) 123-456');

        $this->assertSame('https://wa.me/447911123456', $contact['whatsapp']);
        $this->assertSame('https://t.me/+447911123456', $contact['telegram']);
        $this->assertSame('+447911123456', $contact['tel']);
    }

    public function test_nothing_but_phone_characters_can_reach_a_link_or_the_display_text(): void
    {
        // A value from a website form / free-text field must never smuggle markup into an href or the page.
        foreach ([
            '+44<script>alert(1)</script>7911123456',
            '(555) 123-4567 <b onmouseover=x>',
            '+44 7911 123456"><img src=x onerror=alert(1)>',
            'javascript:alert(1)//+447911123456',
        ] as $hostile) {
            $contact = ClientContact::from($hostile);

            if ($contact === null) {
                continue;
            }

            foreach (['display', 'tel'] as $field) {
                $this->assertMatchesRegularExpression('/^[\d+()\-.\s]+$/', $contact[$field], "{$field} of {$hostile}");
            }
            foreach (['whatsapp', 'telegram'] as $link) {
                if ($contact[$link] !== null) {
                    $this->assertMatchesRegularExpression('#^https://(wa\.me/\d+|t\.me/\+\d+)$#', $contact[$link]);
                }
            }
        }
    }
}

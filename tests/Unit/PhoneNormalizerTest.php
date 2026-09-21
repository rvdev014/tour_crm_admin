<?php

namespace Tests\Unit;

use App\Support\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_uz(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNormalizer::uz($input));
    }

    public static function cases(): array
    {
        return [
            'e164 as stored by the admin phone input' => ['+998901112233', '+998901112233'],
            'no plus' => ['998901112233', '+998901112233'],
            'national, spaced' => ['90 111 22 33', '+998901112233'],
            'national, punctuated' => ['(90) 111-22-33', '+998901112233'],
            'international, spaced' => ['+998 90 111 22 33', '+998901112233'],

            // Real production row (drivers.id = 3): 9 digits that START with 998. Almost certainly a
            // country code with the rest cut off, not national number 998 xx xx xx — do not guess.
            'real data: truncated 998299221' => ['998299221', null],

            'too short' => ['9011122', null],
            'too long' => ['+9989011122334', null],
            'foreign country code' => ['+79161234567', null],
            'letters only' => ['abc', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    /** @dataProvider telCases */
    public function test_tel_gives_a_dialable_value(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNormalizer::tel($input));
    }

    public static function telCases(): array
    {
        return [
            'normalizable number is normalized' => ['90 111 22 33', '+998901112233'],
            'already e164' => ['+998901112233', '+998901112233'],
            // uz() refuses this as a LOGIN number (ambiguous), but a call button only has to be dialable.
            'refused by uz() falls back to the typed digits' => ['998299221', '998299221'],
            'punctuation is stripped, a leading plus is kept' => ['+7 (916) 123-45-67', '+79161234567'],
            'no digits at all' => ['abc', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    public function test_the_same_number_written_differently_normalizes_identically(): void
    {
        $this->assertSame(
            PhoneNormalizer::uz('+998917786638'),
            PhoneNormalizer::uz('91 778 66 38'),
        );
    }
}

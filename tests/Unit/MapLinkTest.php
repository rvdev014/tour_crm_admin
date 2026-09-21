<?php

namespace Tests\Unit;

use App\Support\MapLink;
use PHPUnit\Framework\TestCase;

class MapLinkTest extends TestCase
{
    public function test_coordinates_are_swapped_to_the_lon_lat_order_yandex_expects(): void
    {
        // We store "lat,lng" (Tashkent = 41.3, 69.25). Yandex's ll/pt take "lon,lat". Getting this
        // backwards drops the pin in the wrong hemisphere, so pin it down.
        $url = MapLink::yandex('ignored when coordinates exist', '41.31627274, 69.25246429');

        $this->assertSame(
            'https://yandex.uz/maps/?ll=69.252464,41.316273&z=16&pt=69.252464,41.316273',
            $url,
        );
    }

    public function test_coordinates_win_over_the_address_and_the_city_hint(): void
    {
        $url = MapLink::yandex('Registon Plaza', '39.654,66.975', 'Samarkand');

        $this->assertStringContainsString('ll=66.975000,39.654000', $url);
        $this->assertStringNotContainsString('text=', $url);
    }

    public function test_text_search_is_url_encoded(): void
    {
        $this->assertSame(
            'https://yandex.uz/maps/?text=Registon%20Plaza',
            MapLink::yandex('Registon Plaza'),
        );
    }

    public function test_the_city_is_prepended_so_the_place_resolves_in_the_right_city(): void
    {
        $this->assertSame(
            'https://yandex.uz/maps/?text='.rawurlencode('Samarkand, Registon Plaza'),
            MapLink::yandex('Registon Plaza', null, 'Samarkand'),
        );
    }

    public function test_the_city_is_not_repeated_when_the_address_already_names_it(): void
    {
        $this->assertSame(
            'https://yandex.uz/maps/?text='.rawurlencode('Samarkand Airport, terminal 2'),
            MapLink::yandex('Samarkand Airport, terminal 2', null, 'samarkand'),
        );
    }

    public function test_cyrillic_and_special_characters_survive_encoding(): void
    {
        $url = MapLink::yandex('Регистан & «Шердор»', null, 'Самарканд');

        $this->assertSame('https://yandex.uz/maps/?text='.rawurlencode('Самарканд, Регистан & «Шердор»'), $url);
        $this->assertStringNotContainsString(' ', $url);
        $this->assertStringNotContainsString('&', substr($url, strlen('https://yandex.uz/maps/?text=')));
    }

    public function test_malformed_coordinates_fall_back_to_the_address(): void
    {
        $this->assertSame(
            'https://yandex.uz/maps/?text=Registon',
            MapLink::yandex('Registon', 'not,coords'),
        );
        $this->assertSame(
            'https://yandex.uz/maps/?text=Registon',
            MapLink::yandex('Registon', '41.3'),
        );
    }

    /** @dataProvider nothingToPointAt */
    public function test_returns_null_when_there_is_nothing_to_point_at(?string $address, ?string $coords): void
    {
        $this->assertNull(MapLink::yandex($address, $coords, 'Samarkand'));
    }

    public static function nothingToPointAt(): array
    {
        return [
            'nothing' => [null, null],
            'empty address' => ['', null],
            'blank address' => ['   ', null],
            'bad coords and no address' => [null, 'x,y'],
        ];
    }
}

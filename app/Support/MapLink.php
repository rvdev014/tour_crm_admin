<?php

namespace App\Support;

use App\Services\GeoDistance;

class MapLink
{
    /**
     * Link that opens Yandex Maps on a place. No API key or billing involved. On a phone with the
     * Yandex Maps app installed, the app takes over the link.
     *
     * Exact coordinates win when we have them; otherwise the address is searched as text. Returns
     * null when there is nothing to point at, so the caller can omit the button.
     *
     * @param  string|null  $coords  "lat,lng" — the format transfer_requests.from_coords/to_coords use
     * @param  string|null  $cityHint  prepended to a text search so "Registon Plaza" resolves in the
     *                                 right city instead of anywhere in the world
     */
    public static function yandex(?string $address, ?string $coords = null, ?string $cityHint = null): ?string
    {
        if ($point = GeoDistance::parseCoords($coords)) {
            [$lat, $lng] = $point;

            // Yandex takes "longitude,latitude" — the REVERSE of how we store coordinates.
            $lonLat = number_format($lng, 6, '.', '').','.number_format($lat, 6, '.', '');

            return "https://yandex.uz/maps/?ll={$lonLat}&z=16&pt={$lonLat}";
        }

        $address = trim((string) $address);
        if ($address === '') {
            return null;
        }

        $cityHint = trim((string) $cityHint);
        // Skip the hint when the address already names the city ("Samarkand Airport" + "Samarkand").
        if ($cityHint !== '' && mb_stripos($address, $cityHint) === false) {
            $address = $cityHint.', '.$address;
        }

        return 'https://yandex.uz/maps/?text='.rawurlencode($address);
    }
}

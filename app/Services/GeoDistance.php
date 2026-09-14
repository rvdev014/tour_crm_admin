<?php

namespace App\Services;

class GeoDistance
{
    /**
     * Great-circle distance in kilometers between two lat/lng points.
     *
     * Deliberately the same formula as the frontend's
     * src/shared/utils/distance-calculator.ts (calculateDistance), so
     * moving distance calculation server-side does not shift prices for
     * legitimate users — it only stops a client from supplying its own
     * (possibly false) distance.
     */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * Parses a "lat,lng" or "lat, lng" coordinate string as used by
     * transfer_requests.from_coords / to_coords.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function parseCoords(?string $coords): ?array
    {
        if (empty($coords)) {
            return null;
        }

        $parts = array_map('trim', explode(',', $coords));
        if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            return null;
        }

        return [(float) $parts[0], (float) $parts[1]];
    }

    public static function distanceBetweenCoordStrings(string $fromCoords, string $toCoords): ?float
    {
        $from = self::parseCoords($fromCoords);
        $to = self::parseCoords($toCoords);

        if ($from === null || $to === null) {
            return null;
        }

        return self::haversineKm($from[0], $from[1], $to[0], $to[1]);
    }
}

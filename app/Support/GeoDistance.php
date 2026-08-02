<?php

namespace App\Support;

/**
 * Great-circle distance between two WGS-84 coordinates (Haversine).
 */
final class GeoDistance
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    /**
     * @return float Distance in meters (non-negative).
     */
    public static function metersBetween(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude,
    ): float {
        $latFrom = deg2rad($fromLatitude);
        $lonFrom = deg2rad($fromLongitude);
        $latTo = deg2rad($toLatitude);
        $lonTo = deg2rad($toLongitude);

        $deltaLat = $latTo - $latFrom;
        $deltaLon = $lonTo - $lonFrom;

        $a = sin($deltaLat / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }
}

<?php

namespace App\Support;

/**
 * Pure helpers for detecting low movement from GPS samples.
 */
final class LocationIdleDetector
{
    /**
     * @param  list<array{latitude: float, longitude: float, recorded_at?: mixed}>  $samples
     * @return array{
     *     is_idle: bool,
     *     max_displacement_meters: float,
     *     center_latitude: float|null,
     *     center_longitude: float|null,
     *     sample_count: int,
     * }
     */
    public static function analyze(array $samples, float $maxDisplacementMeters): array
    {
        $count = count($samples);
        if ($count === 0) {
            return [
                'is_idle' => false,
                'max_displacement_meters' => 0.0,
                'center_latitude' => null,
                'center_longitude' => null,
                'sample_count' => 0,
            ];
        }

        $sumLat = 0.0;
        $sumLng = 0.0;
        foreach ($samples as $sample) {
            $sumLat += (float) $sample['latitude'];
            $sumLng += (float) $sample['longitude'];
        }

        $centerLat = $sumLat / $count;
        $centerLng = $sumLng / $count;

        $maxDisplacement = 0.0;
        foreach ($samples as $sample) {
            $distance = GeoDistance::metersBetween(
                $centerLat,
                $centerLng,
                (float) $sample['latitude'],
                (float) $sample['longitude'],
            );
            if ($distance > $maxDisplacement) {
                $maxDisplacement = $distance;
            }
        }

        $maxDisplacement = round($maxDisplacement, 2);

        return [
            'is_idle' => $maxDisplacement <= $maxDisplacementMeters,
            'max_displacement_meters' => $maxDisplacement,
            'center_latitude' => round($centerLat, 7),
            'center_longitude' => round($centerLng, 7),
            'sample_count' => $count,
        ];
    }
}

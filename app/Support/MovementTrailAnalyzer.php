<?php

namespace App\Support;

/**
 * Summarise a mid-shift GPS trail: distance walked, waiting, path segments.
 */
final class MovementTrailAnalyzer
{
    /**
     * @param  list<array{latitude: float|int|string, longitude: float|int|string, recorded_at?: string|null}>  $trail
     * @param  list<array<string, mixed>>  $idleAlerts
     * @return array{
     *     sample_count: int,
     *     distance_meters: float,
     *     distance_label: string,
     *     waiting_minutes: int,
     *     waiting_label: string,
     *     longest_wait_minutes: int,
     *     is_currently_waiting: bool,
     *     duration_minutes: int,
     *     segments: list<array{
     *         from_lat: float,
     *         from_lng: float,
     *         to_lat: float,
     *         to_lng: float,
     *         mid_lat: float,
     *         mid_lng: float,
     *         bearing_degrees: float,
     *         distance_meters: float,
     *         from_at: string|null,
     *         to_at: string|null,
     *     }>,
     * }
     */
    public static function analyze(array $trail, array $idleAlerts = [], float $waitDisplacementMeters = 25.0): array
    {
        $points = [];
        foreach ($trail as $row) {
            $lat = isset($row['latitude']) ? (float) $row['latitude'] : null;
            $lng = isset($row['longitude']) ? (float) $row['longitude'] : null;
            if ($lat === null || $lng === null || ! is_finite($lat) || ! is_finite($lng)) {
                continue;
            }
            $points[] = [
                'latitude' => $lat,
                'longitude' => $lng,
                'recorded_at' => isset($row['recorded_at']) && is_string($row['recorded_at'])
                    ? $row['recorded_at']
                    : null,
            ];
        }

        $distance = 0.0;
        $segments = [];
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $from = $points[$i - 1];
            $to = $points[$i];
            $step = GeoDistance::metersBetween(
                $from['latitude'],
                $from['longitude'],
                $to['latitude'],
                $to['longitude'],
            );
            // Ignore tiny GPS jitter under 2m between samples for distance total.
            if ($step >= 2.0) {
                $distance += $step;
            }

            if ($step >= 5.0) {
                $segments[] = [
                    'from_lat' => $from['latitude'],
                    'from_lng' => $from['longitude'],
                    'to_lat' => $to['latitude'],
                    'to_lng' => $to['longitude'],
                    'mid_lat' => ($from['latitude'] + $to['latitude']) / 2,
                    'mid_lng' => ($from['longitude'] + $to['longitude']) / 2,
                    'bearing_degrees' => round(self::bearingDegrees(
                        $from['latitude'],
                        $from['longitude'],
                        $to['latitude'],
                        $to['longitude'],
                    ), 1),
                    'distance_meters' => round($step, 1),
                    'from_at' => $from['recorded_at'],
                    'to_at' => $to['recorded_at'],
                ];
            }
        }

        $waitingMinutes = 0;
        $isCurrentlyWaiting = false;
        if (count($points) >= 2) {
            $window = array_slice($points, -max(3, (int) ceil(count($points) / 3)));
            $analysis = LocationIdleDetector::analyze($window, $waitDisplacementMeters);
            if ($analysis['is_idle']) {
                $firstAt = $window[0]['recorded_at'] ?? null;
                $lastAt = $window[array_key_last($window)]['recorded_at'] ?? null;
                if ($firstAt && $lastAt) {
                    $waitingMinutes = max(0, (int) round((strtotime($lastAt) - strtotime($firstAt)) / 60));
                    $isCurrentlyWaiting = true;
                }
            }
        }

        $longestWait = $waitingMinutes;
        foreach ($idleAlerts as $alert) {
            $mins = (int) ($alert['idle_minutes'] ?? 0);
            if ($mins > $longestWait) {
                $longestWait = $mins;
            }
        }

        $durationMinutes = 0;
        if (count($points) >= 2) {
            $start = $points[0]['recorded_at'] ?? null;
            $end = $points[array_key_last($points)]['recorded_at'] ?? null;
            if ($start && $end) {
                $durationMinutes = max(0, (int) round((strtotime($end) - strtotime($start)) / 60));
            }
        }

        $distanceRounded = round($distance, 1);

        return [
            'sample_count' => count($points),
            'distance_meters' => $distanceRounded,
            'distance_label' => self::formatDistance($distanceRounded),
            'waiting_minutes' => $waitingMinutes,
            'waiting_label' => self::formatMinutes($waitingMinutes),
            'longest_wait_minutes' => $longestWait,
            'is_currently_waiting' => $isCurrentlyWaiting,
            'duration_minutes' => $durationMinutes,
            'segments' => $segments,
        ];
    }

    public static function formatDistance(float $meters): string
    {
        if ($meters < 1) {
            return '0 m';
        }
        if ($meters < 1000) {
            return round($meters).' m';
        }

        return round($meters / 1000, 2).' km';
    }

    public static function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 min';
        }
        if ($minutes < 60) {
            return $minutes.' min';
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;

        return $rem > 0 ? "{$hours}h {$rem}m" : "{$hours}h";
    }

    private static function bearingDegrees(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
    ): float {
        $φ1 = deg2rad($fromLat);
        $φ2 = deg2rad($toLat);
        $Δλ = deg2rad($toLng - $fromLng);

        $y = sin($Δλ) * cos($φ2);
        $x = cos($φ1) * sin($φ2) - sin($φ1) * cos($φ2) * cos($Δλ);
        $θ = rad2deg(atan2($y, $x));

        return fmod(($θ + 360.0), 360.0);
    }
}

<?php

namespace App\Support;

use App\Models\TimeClockEntry;
use Illuminate\Support\Collection;

final class AdminTimeClockDisplay
{
    public static function punchSourceLabel(?string $punchSource): string
    {
        return match ($punchSource) {
            TimeClockEntry::PUNCH_SOURCE_AUTO_GEOFENCE_EXIT => 'Auto (left site)',
            TimeClockEntry::PUNCH_SOURCE_MANUAL, null, '' => 'Manual',
            default => ucfirst(str_replace('_', ' ', $punchSource)),
        };
    }

    public static function punchSourceBadgeClasses(?string $punchSource): string
    {
        return match ($punchSource) {
            TimeClockEntry::PUNCH_SOURCE_AUTO_GEOFENCE_EXIT => 'bg-amber-50 text-amber-900 ring-amber-200',
            default => 'bg-slate-50 text-slate-700 ring-slate-200',
        };
    }

    public static function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            TimeClockEntry::EVENT_CLOCK_IN => 'Clock in',
            TimeClockEntry::EVENT_CLOCK_OUT => 'Clock out',
            default => ucfirst(str_replace('_', ' ', $eventType)),
        };
    }

    public static function eventBadgeClasses(string $eventType): string
    {
        return match ($eventType) {
            TimeClockEntry::EVENT_CLOCK_IN => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            TimeClockEntry::EVENT_CLOCK_OUT => 'bg-slate-100 text-slate-800 ring-slate-200',
            default => 'bg-brand-surface text-brand-text ring-brand-border',
        };
    }

    public static function formatCoords(?float $lat, ?float $lng): string
    {
        if ($lat === null || $lng === null) {
            return '—';
        }

        return sprintf('%.5f, %.5f', $lat, $lng);
    }

    public static function formatDistance(?float $meters): string
    {
        if ($meters === null) {
            return '—';
        }

        if ($meters < 1000) {
            return sprintf('%.0f m', $meters);
        }

        return sprintf('%.2f km', $meters / 1000);
    }

    public static function resolveDistanceMeters(?TimeClockEntry $entry): ?float
    {
        if (! $entry instanceof TimeClockEntry) {
            return null;
        }

        if ($entry->distance_from_site_meters !== null) {
            return (float) $entry->distance_from_site_meters;
        }

        if ($entry->device_latitude === null
            || $entry->device_longitude === null
            || $entry->expected_latitude === null
            || $entry->expected_longitude === null) {
            return null;
        }

        return round(GeoDistance::metersBetween(
            (float) $entry->device_latitude,
            (float) $entry->device_longitude,
            (float) $entry->expected_latitude,
            (float) $entry->expected_longitude,
        ), 2);
    }

    public static function formatDistanceMetersInteger(?TimeClockEntry $entry): string
    {
        $meters = self::resolveDistanceMeters($entry);

        if ($meters === null) {
            return '—';
        }

        return number_format($meters, 0, '.', '');
    }

    public static function toDatetimeLocalValue(?\Carbon\CarbonInterface $at): string
    {
        if ($at === null) {
            return '';
        }

        return DisplayTimezone::format($at, 'Y-m-d\TH:i');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function punchMapPayload(?TimeClockEntry $entry): ?array
    {
        if (! $entry instanceof TimeClockEntry) {
            return null;
        }

        if ($entry->device_latitude === null || $entry->device_longitude === null) {
            return null;
        }

        $within = (bool) $entry->within_geofence;
        $resolvedDistance = self::resolveDistanceMeters($entry);
        $distanceLabel = $resolvedDistance !== null
            ? self::formatDistance($resolvedDistance)
            : '—';
        $allowedRadius = $entry->allowed_radius_meters
            ?? (int) config('time_clock.geofence_radius_meters', 100);
        $eventLabel = self::eventLabel($entry->event_type);

        return [
            'event' => $entry->event_type,
            'event_label' => $eventLabel,
            'time_label' => $entry->clocked_at !== null
                ? DisplayTimezone::format($entry->clocked_at, 'g:i A')
                : '—',
            'date_label' => $entry->clocked_at !== null
                ? DisplayTimezone::format($entry->clocked_at, 'M j, Y')
                : '—',
            'within_geofence' => $within,
            'geofence_label' => $within ? 'Within designated radius' : 'Outside designated radius',
            'device_latitude' => (float) $entry->device_latitude,
            'device_longitude' => (float) $entry->device_longitude,
            'expected_latitude' => $entry->expected_latitude !== null ? (float) $entry->expected_latitude : null,
            'expected_longitude' => $entry->expected_longitude !== null ? (float) $entry->expected_longitude : null,
            'distance_label' => $distanceLabel,
            'allowed_radius_meters' => $allowedRadius,
            'device_coords_label' => self::formatCoords($entry->device_latitude, $entry->device_longitude),
            'expected_coords_label' => self::formatCoords($entry->expected_latitude, $entry->expected_longitude),
            'icon_tone' => $within ? 'within' : 'outside',
            'icon_title' => sprintf(
                '%s at %s — %s (%s from site, allowed %d m)',
                $eventLabel,
                $entry->clocked_at !== null ? DisplayTimezone::format($entry->clocked_at, 'g:i A') : '—',
                $within ? 'within radius' : 'outside radius',
                $distanceLabel,
                $allowedRadius,
            ),
        ];
    }

    public static function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        if ($minutes > 0) {
            return sprintf('%dm', $minutes);
        }

        return '< 1m';
    }

    /**
     * Pair clock-in / clock-out punches chronologically and compute per-session hours.
     *
     * @param  Collection<int, TimeClockEntry>  $entries
     * @return array{
     *     hours_by_entry_id: array<int, array{
     *         label: string,
     *         seconds: int,
     *         is_open: bool,
     *         clock_in_id: int,
     *         clock_out_id: int|null,
     *         range_label: string,
     *     }>,
     *     total_seconds: int,
     *     completed_sessions: int,
     * }
     */
    public static function summarizeWorkSessions(Collection $entries): array
    {
        $sorted = $entries
            ->sortBy(static fn (TimeClockEntry $entry) => [
                $entry->clocked_at?->getTimestamp() ?? 0,
                $entry->id,
            ])
            ->values();

        $hoursByEntryId = [];
        $totalSeconds = 0;
        $completedSessions = 0;
        $openClockIn = null;

        foreach ($sorted as $entry) {
            if ($entry->event_type === TimeClockEntry::EVENT_CLOCK_IN) {
                $openClockIn = $entry;

                continue;
            }

            if ($entry->event_type !== TimeClockEntry::EVENT_CLOCK_OUT || $openClockIn === null) {
                continue;
            }

            $seconds = (int) $openClockIn->clocked_at?->diffInSeconds($entry->clocked_at);
            $payload = [
                'label' => self::formatDuration($seconds),
                'seconds' => $seconds,
                'is_open' => false,
                'clock_in_id' => $openClockIn->id,
                'clock_out_id' => $entry->id,
                'range_label' => self::formatSessionRange($openClockIn->clocked_at, $entry->clocked_at),
            ];

            $hoursByEntryId[$entry->id] = $payload;
            $totalSeconds += $seconds;
            $completedSessions++;
            $openClockIn = null;
        }

        if ($openClockIn instanceof TimeClockEntry && $openClockIn->clocked_at !== null) {
            $seconds = (int) $openClockIn->clocked_at->diffInSeconds(now('UTC'));
            $hoursByEntryId[$openClockIn->id] = [
                'label' => self::formatDuration($seconds),
                'seconds' => $seconds,
                'is_open' => true,
                'clock_in_id' => $openClockIn->id,
                'clock_out_id' => null,
                'range_label' => self::formatSessionRange($openClockIn->clocked_at, null),
            ];
            $totalSeconds += $seconds;
        }

        return [
            'hours_by_entry_id' => $hoursByEntryId,
            'total_seconds' => $totalSeconds,
            'completed_sessions' => $completedSessions,
        ];
    }

    public static function formatSessionRange(?\Carbon\CarbonInterface $clockInAt, ?\Carbon\CarbonInterface $clockOutAt): string
    {
        if ($clockInAt === null) {
            return '—';
        }

        $tz = DisplayTimezone::name();
        $start = $clockInAt->timezone($tz);

        if ($clockOutAt === null) {
            return sprintf('Since %s', $start->format('M j, g:i A'));
        }

        $end = $clockOutAt->timezone($tz);
        $sameDay = $start->isSameDay($end);

        if ($sameDay) {
            return sprintf('%s → %s', $start->format('g:i A'), $end->format('g:i A'));
        }

        return sprintf(
            '%s → %s',
            $start->format('M j, g:i A'),
            $end->format('M j, g:i A'),
        );
    }
}

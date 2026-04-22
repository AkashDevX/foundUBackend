<?php

namespace App\Support;

/**
 * Turns registration JSON payloads into admin-friendly labels and structured rows for Blade.
 */
final class RegistrationDisplay
{
    /**
     * @return list<array{label: string, lines: list<string>}>
     */
    public static function weeklyAvailabilitySections(?array $raw): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        // Single associative map of day → availability (common mobile pattern)
        if (self::isAssoc($raw) && ! self::looksLikeDocumentRow($raw)) {
            return [self::sectionFromDayMap($raw)];
        }

        // List of day blocks: [ { day, … }, … ]
        if (array_is_list($raw)) {
            $sections = [];
            foreach ($raw as $i => $block) {
                if (! is_array($block)) {
                    continue;
                }
                $label = self::pickDayLabel($block);
                if ($label === '') {
                    $label = 'Day '.((int) $i + 1);
                }
                $lines = self::linesFromDayBlock($block);
                if ($lines !== []) {
                    $sections[] = ['label' => $label, 'lines' => $lines];
                }
            }

            return $sections !== [] ? $sections : [self::fallbackSection($raw)];
        }

        return [self::fallbackSection($raw)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{title: string, subtitle: string, meta: list<string>, storage_path: ?string, row_key: ?string}>
     */
    public static function idDocumentRows(?array $rows): array
    {
        return self::mapDocumentRows($rows, 'documentKey');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{title: string, subtitle: string, meta: list<string>, storage_path: ?string, row_key: ?string}>
     */
    public static function licenceRows(?array $rows): array
    {
        return self::mapDocumentRows($rows, 'id');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{title: string, subtitle: string, meta: list<string>, storage_path: ?string, row_key: ?string}>
     */
    public static function insuranceRows(?array $rows): array
    {
        return self::mapDocumentRows($rows, 'id');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function metaLinesForDocumentRow(array $row): array
    {
        $skip = ['storage_path', 'documentKey', 'id', 'uri', 'localUri'];
        $lines = [];
        foreach ($row as $key => $value) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            $lines[] = self::humanKey($key).': '.self::scalarOrShortText($value);
        }

        return array_values(array_filter($lines, static fn (string $l): bool => $l !== ''));
    }

    public static function isLikelyImagePath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
    }

    public static function isLikelyPdfPath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     */
    private static function mapDocumentRows(?array $rows, string $keyField): array
    {
        if ($rows === null || $rows === []) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = isset($row[$keyField]) && is_scalar($row[$keyField])
                ? (string) $row[$keyField]
                : '';
            $title = self::pickDocumentTitle($row) ?: ($key !== '' ? 'Document '.$key : 'Document');
            $subtitle = self::pickDocumentSubtitle($row);
            $meta = self::metaLinesForDocumentRow($row);
            $path = isset($row['storage_path']) && is_string($row['storage_path']) ? $row['storage_path'] : null;

            $out[] = [
                'title' => $title,
                'subtitle' => $subtitle,
                'meta' => $meta,
                'storage_path' => $path,
                'row_key' => $key !== '' ? $key : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function pickDocumentTitle(array $row): string
    {
        foreach (['documentType', 'document_type', 'type', 'name', 'title', 'label'] as $k) {
            if (! empty($row[$k]) && is_scalar($row[$k])) {
                return trim((string) $row[$k]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function pickDocumentSubtitle(array $row): string
    {
        foreach (['description', 'notes', 'detail'] as $k) {
            if (! empty($row[$k]) && is_scalar($row[$k])) {
                return trim((string) $row[$k]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array{label: string, lines: list<string>}
     */
    private static function sectionFromDayMap(array $map): array
    {
        $lines = [];
        foreach ($map as $day => $value) {
            $lines[] = self::humanDay((string) $day).': '.self::formatAvailabilityValue($value);
        }

        return ['label' => 'Weekly availability', 'lines' => $lines];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<string>
     */
    private static function linesFromDayBlock(array $block): array
    {
        $lines = [];

        foreach (['slots', 'times', 'ranges', 'hours', 'intervals'] as $k) {
            if (! isset($block[$k])) {
                continue;
            }
            $v = $block[$k];
            if (is_array($v)) {
                $flattened = self::flattenSlots($v);
                if ($flattened !== []) {
                    foreach ($flattened as $slot) {
                        $lines[] = $slot;
                    }
                }
            }
        }

        foreach ($block as $key => $value) {
            if (in_array($key, ['day', 'dayName', 'weekday', 'label', 'slots', 'times', 'ranges', 'hours', 'intervals'], true)) {
                continue;
            }
            $lines[] = self::humanKey((string) $key).': '.self::formatAvailabilityValue($value);
        }

        return array_values(array_unique(array_filter($lines)));
    }

    /**
     * @param  array<mixed>  $slots
     * @return list<string>
     */
    private static function flattenSlots(array $slots): array
    {
        $out = [];
        foreach ($slots as $slot) {
            if (is_string($slot) || is_numeric($slot)) {
                $out[] = (string) $slot;

                continue;
            }
            if (! is_array($slot)) {
                continue;
            }
            $start = $slot['start'] ?? $slot['from'] ?? null;
            $end = $slot['end'] ?? $slot['to'] ?? null;
            if ($start !== null && $end !== null) {
                $out[] = trim((string) $start).' – '.trim((string) $end);

                continue;
            }
            $parts = [];
            foreach ($slot as $k => $v) {
                if (is_scalar($v)) {
                    $parts[] = self::humanKey((string) $k).': '.$v;
                }
            }
            if ($parts !== []) {
                $out[] = implode(' · ', $parts);
            }
        }

        return $out;
    }

    private static function pickDayLabel(array $block): string
    {
        foreach (['dayName', 'day', 'weekday', 'label', 'name'] as $k) {
            if (! empty($block[$k]) && is_scalar($block[$k])) {
                return self::humanDay(trim((string) $block[$k]));
            }
        }

        return '';
    }

    /**
     * @param  array<mixed>  $raw
     * @return array{label: string, lines: list<string>}
     */
    private static function fallbackSection(array $raw): array
    {
        $lines = [];
        foreach ($raw as $key => $value) {
            if (is_int($key)) {
                $lines[] = self::formatAvailabilityValue($value);

                continue;
            }
            $lines[] = self::humanKey((string) $key).': '.self::formatAvailabilityValue($value);
        }

        return ['label' => 'Availability details', 'lines' => array_values(array_filter($lines, static fn ($l) => $l !== '' && $l !== '—'))];
    }

    private static function formatAvailabilityValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Available' : 'Not available';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return trim($value) === '' ? '—' : trim($value);
        }
        if (is_array($value)) {
            if ($value === []) {
                return '—';
            }
            if (array_is_list($value)) {
                $parts = [];
                foreach ($value as $item) {
                    $parts[] = self::formatAvailabilityValue($item);
                }

                return implode(', ', array_filter($parts, static fn ($p) => $p !== '—'));
            }

            $pairs = [];
            foreach ($value as $k => $v) {
                $pairs[] = self::humanKey((string) $k).': '.self::formatAvailabilityValue($v);
            }

            return implode('; ', $pairs);
        }

        return '—';
    }

    private static function scalarOrShortText(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_scalar($value)) {
            return trim((string) $value);
        }
        if (is_array($value)) {
            return self::formatAvailabilityValue($value);
        }

        return '—';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function looksLikeDocumentRow(array $row): bool
    {
        return isset($row['storage_path']) || isset($row['documentKey']) || isset($row['documentType']);
    }

    /**
     * @param  array<mixed>  $arr
     */
    private static function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private static function humanDay(string $key): string
    {
        $k = strtolower(str_replace(['_', '-'], ' ', $key));

        return ucwords($k);
    }

    private static function humanKey(string $key): string
    {
        $k = str_replace(['_', '-'], ' ', $key);

        return ucwords(trim($k));
    }
}

<?php

namespace App\Support;

use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;

/**
 * Turns registration JSON payloads into admin-friendly labels and structured rows for Blade.
 */
final class RegistrationDisplay
{
    /**
     * Use model data when the last flashed request did not include this field (e.g. a different
     * form on the same page was posted). {@see Request::old} cannot do that
     * because it returns null when the key exists with a null value.
     */
    public static function oldOrAttribute(string $key, mixed $fallback): mixed
    {
        if (! app()->bound('request')) {
            return $fallback;
        }
        $request = request();
        if (! $request->hasSession()) {
            return $fallback;
        }
        try {
            $old = $request->session()->getOldInput();
        } catch (\Throwable) {
            return $fallback;
        }
        if (! is_array($old) || ! array_key_exists($key, $old)) {
            return $fallback;
        }

        return $old[$key];
    }

    /**
     * Column value, or the same key from `profile_metadata` (camelCase from mobile) when empty.
     *
     * @param  list<string>  $metadataKeys
     */
    public static function employeeRawDateValue(Employee $employee, string $column, array $metadataKeys = []): mixed
    {
        $attrs = $employee->getAttributes();
        $v = $attrs[$column] ?? null;
        if (($v === null || (is_string($v) && trim($v) === '')) && method_exists($employee, 'getRawOriginal')) {
            try {
                $raw = $employee->getRawOriginal($column);
                if ($raw !== null && (! is_string($raw) || trim($raw) !== '')) {
                    $v = $raw;
                }
            } catch (\Throwable) {
            }
        }
        if ($v !== null && ! is_string($v)) {
            return $v;
        }
        if (is_string($v) && trim($v) !== '') {
            return $v;
        }

        $meta = $employee->profile_metadata;
        if (! is_array($meta)) {
            $rj = $attrs['profile_metadata'] ?? null;
            if (is_string($rj) && $rj !== '') {
                $decoded = json_decode($rj, true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
        }
        if (! is_array($meta)) {
            $rj = self::databaseColumnValue($employee, 'profile_metadata');
            if (is_string($rj) && $rj !== '') {
                $decoded = json_decode($rj, true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
        }
        if (! is_array($meta)) {
            $meta = [];
        }
        foreach ($metadataKeys as $k) {
            if (! array_key_exists($k, $meta)) {
                continue;
            }
            $mv = $meta[$k];
            if ($mv === null || $mv === '' || $mv === []) {
                continue;
            }

            return $mv;
        }

        $nested = self::firstScalarMatchInTree($meta, $metadataKeys);
        if ($nested !== null && $nested !== '') {
            return $nested;
        }

        if ($column === 'date_of_birth') {
            $fromDocs = self::firstScalarMatchInDocumentRows($employee->id_documents_json ?? null, $metadataKeys);
            if ($fromDocs !== null && $fromDocs !== '') {
                return $fromDocs;
            }
        }

        $direct = self::databaseColumnValue($employee, $column);
        if ($direct !== null && (! is_string($direct) || trim($direct) !== '')) {
            return $direct;
        }

        return null;
    }

    /**
     * Admin registration form &lt;input type="date" value="…"&gt; — resolves DB / metadata, then flashed old input on validation failure.
     *
     * @param  list<string>  $metadataKeys
     */
    public static function adminDateInputValue(Request $request, Employee $employee, string $column, array $metadataKeys): string
    {
        $fromModel = self::employeeRawDateValue($employee, $column, $metadataKeys);
        $errors = $request->session()->get('errors');
        if ($errors instanceof ViewErrorBag && $errors->any()) {
            $old = $request->session()->getOldInput();
            if (is_array($old) && array_key_exists($column, $old)) {
                $ov = $old[$column];
                if ($ov !== null && $ov !== '') {
                    return self::toHtmlDateInput($ov);
                }
                // Flashed empty string (stale session / other form) must not hide a persisted DB date
            }
        }

        return self::toHtmlDateInput($fromModel);
    }

    public static function adminAssignmentEffectiveInput(Request $request, Employee $employee): string
    {
        $fromModel = $employee->assignment_effective_from;
        $errors = $request->session()->get('errors');
        if ($errors instanceof ViewErrorBag && $errors->any()) {
            $old = $request->session()->getOldInput();
            if (is_array($old) && array_key_exists('assignment_effective_from', $old)) {
                $ov = $old['assignment_effective_from'];
                if ($ov !== null && $ov !== '') {
                    return self::toHtmlDateInput($ov);
                }
            }
        }

        return self::toHtmlDateInput($fromModel);
    }

    public static function resetDatabaseRowCache(): void
    {
        self::$databaseRowCacheKey = null;
        self::$databaseRowCache = null;
    }

    /**
     * Fills still-empty admin date strings from the raw tenant row (avoids Eloquent/cast gaps).
     *
     * @param  array<string, string>  $inputs
     * @return array<string, string>
     */
    public static function mergeRegistrationDatesFromDatabase(string $connection, string $publicId, array $inputs): array
    {
        try {
            $row = DB::connection($connection)->table('employees')->where('public_id', $publicId)->first();
            if ($row === null) {
                return $inputs;
            }
            $arr = (array) $row;
            foreach (array_keys(self::adminProfileDateMetadataKeys()) as $col) {
                if (! array_key_exists($col, $arr)) {
                    continue;
                }
                $iso = self::toHtmlDateInput($arr[$col]);
                if ($iso === '') {
                    continue;
                }
                if (($inputs[$col] ?? '') === '') {
                    $inputs[$col] = $iso;
                }
            }
            if (array_key_exists('assignment_effective_from', $arr)) {
                $iso = self::toHtmlDateInput($arr['assignment_effective_from']);
                if ($iso !== '' && (($inputs['assignment_effective_from'] ?? '') === '')) {
                    $inputs['assignment_effective_from'] = $iso;
                }
            }

            return $inputs;
        } catch (\Throwable) {
            return $inputs;
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public static function adminProfileDateMetadataKeys(): array
    {
        return [
            'date_of_birth' => ['dateOfBirth', 'date_of_birth', 'dob', 'birthDate'],
            'visa_expiry' => ['visaExpiry', 'visa_expiry'],
            'police_check_expiry' => ['policeCheckExpiry', 'police_check_expiry'],
            'fit_to_work_expiry' => ['fitToWorkExpiry', 'fit_to_work_expiry'],
            'vehicle_expiry' => ['vehicleExpiry', 'vehicle_expiry'],
        ];
    }

    private static ?string $databaseRowCacheKey = null;

    /** @var array<string, mixed>|null */
    private static ?array $databaseRowCache = null;

    /**
     * Last-resort read straight from the tenant row (bypasses attribute casting / hydration quirks).
     * Caches the full row for one employee per request so five date fields do not run five queries.
     */
    private static function databaseColumnValue(Employee $employee, string $column): mixed
    {
        if (! preg_match('/^[a-z0-9_]+$/i', $column)) {
            return null;
        }
        try {
            $conn = $employee->getConnectionName();
            $table = $employee->getTable();
            $keyName = $employee->getKeyName();
            $key = $employee->getKey();
            if ($conn === null || $conn === '' || $table === '' || $key === null) {
                return null;
            }
            $cacheKey = $conn.'|'.$table.'|'.$keyName.'|'.(string) $key;
            if (self::$databaseRowCacheKey !== $cacheKey || self::$databaseRowCache === null) {
                $row = DB::connection($conn)->table($table)->where($keyName, $key)->first();
                self::$databaseRowCache = $row === null ? [] : (array) $row;
                self::$databaseRowCacheKey = $cacheKey;
            }

            return self::$databaseRowCache[$column] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $keys
     */
    private static function firstScalarMatchInTree(mixed $node, array $keys, int $depth = 0): mixed
    {
        if ($depth > 14 || ! is_array($node)) {
            return null;
        }
        foreach ($keys as $k) {
            if (! array_key_exists($k, $node)) {
                continue;
            }
            $v = $node[$k];
            if ($v === null || $v === '' || $v === []) {
                continue;
            }

            return $v;
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                $found = self::firstScalarMatchInTree($child, $keys, $depth + 1);
                if ($found !== null && $found !== '') {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>|null  $rows
     * @param  list<string>  $keys
     */
    private static function firstScalarMatchInDocumentRows(?array $rows, array $keys): mixed
    {
        if ($rows === null || $rows === []) {
            return null;
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($keys as $k) {
                if (! array_key_exists($k, $row)) {
                    continue;
                }
                $v = $row[$k];
                if ($v === null || $v === '' || $v === []) {
                    continue;
                }

                return $v;
            }
            $nested = self::firstScalarMatchInTree($row, $keys);
            if ($nested !== null && $nested !== '') {
                return $nested;
            }
        }

        return null;
    }

    /**
     * Normalize a stored or submitted date to Y-m-d, or null if empty / unparseable.
     * Use for API JSON and persisting so clients and HTML date inputs always see ISO calendar dates.
     */
    public static function toNullableIsoDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $f = (float) $value;
            if ($f == 0.0) {
                return null;
            }
            if ($f > 1.0e12) {
                $f = $f / 1000.0;
            }
            $tz = config('app.timezone');
            // Skip bare years like 1990 (treat as string below). Real Unix times for DOB are much larger.
            if (abs($f) > 31_536_000) {
                try {
                    return Carbon::createFromTimestamp((int) $f)->timezone($tz)->format('Y-m-d');
                } catch (\Throwable) {
                }
            }
        }

        if (is_array($value)) {
            if (isset($value['year'], $value['month'], $value['day'])) {
                $y = (int) $value['year'];
                $m = (int) $value['month'];
                $d = (int) $value['day'];
                if ($y > 0 && $m > 0 && $d > 0 && checkdate($m, $d, $y)) {
                    return sprintf('%04d-%02d-%02d', $y, $m, $d);
                }
                if ($y > 0 && $m >= 0 && $m <= 11 && $d > 0) {
                    $m1 = $m + 1;
                    if (checkdate($m1, $d, $y)) {
                        return sprintf('%04d-%02d-%02d', $y, $m1, $d);
                    }
                }
            }
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }
        if ($value instanceof \DateTimeInterface) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        $s = str_replace("\xC2\xA0", ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        // Strip a single pair of surrounding ASCII quotes (stored JSON fragments, copy/paste).
        if (
            strlen($s) >= 2
            && (($s[0] === '"' && $s[strlen($s) - 1] === '"') || ($s[0] === "'" && $s[strlen($s) - 1] === "'"))
        ) {
            $s = trim(substr($s, 1, -1));
        }

        if ($s === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            try {
                $day = Carbon::createFromFormat('Y-m-d', $m[1]);
                if ($day !== false && $day->format('Y-m-d') === $m[1]) {
                    return $m[1];
                }
            } catch (\Throwable) {
            }
        }

        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            try {
                $day = Carbon::createFromFormat('Y-m-d', $m[1]);
                if ($day !== false && $day->format('Y-m-d') === $m[1]) {
                    return $m[1];
                }
            } catch (\Throwable) {
            }
        }

        // Numeric month/day/year with -, /, or . (DB often stores mm-dd-yyyy; HTML date inputs require Y-m-d).
        // Prefer US order (month/day/year) when it is a valid calendar date; otherwise try day/month/year.
        if (preg_match('#^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})\b#', $s, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $year = (int) $m[3];
            if ($year >= 1000 && $year <= 9999) {
                if ($a >= 1 && $a <= 12 && $b >= 1 && $b <= 31 && checkdate($a, $b, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $a, $b);
                }
                if ($b >= 1 && $b <= 12 && $a >= 1 && $a <= 31 && checkdate($b, $a, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $b, $a);
                }
            }
        }

        if (preg_match('/^\d{8}$/', $s)) {
            try {
                return Carbon::createFromFormat('Ymd', $s)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        if (preg_match('/^\d{10,13}$/', $s)) {
            $ts = strlen($s) >= 13
                ? (int) floor((float) $s / 1000)
                : (int) $s;
            if ($ts > 0) {
                try {
                    return Carbon::createFromTimestamp($ts)->timezone(config('app.timezone'))->format('Y-m-d');
                } catch (\Throwable) {
                }
            }
        }

        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
        }

        $formats = [
            'Y-m-d',
            'm-d-Y',
            'n-j-Y',
            'm/d/Y',
            'n/j/Y',
            'd/m/Y',
            'd-m-Y',
            'd.m.Y',
            'j/n/Y',
            'j-n-Y',
            'Y/m/d',
            'Y-m-d H:i:s',
            'Y-m-d H:i:s.u',
            'd/m/y',
            'j M Y',
            'j F Y',
            'M j, Y',
            'F j, Y',
        ];
        foreach ($formats as $fmt) {
            try {
                $parsed = Carbon::createFromFormat($fmt, $s);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * Normalize a stored registration date (string column, ISO fragment, or Carbon) to Y-m-d for HTML date inputs.
     */
    public static function toHtmlDateInput(mixed $value): string
    {
        return self::toNullableIsoDate($value) ?? '';
    }

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

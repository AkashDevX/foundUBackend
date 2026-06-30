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
        $candidates = [];

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
        if ($v !== null && (! is_string($v) || trim($v) !== '')) {
            $candidates[] = $v;
        }

        $direct = self::databaseColumnValue($employee, $column);
        if ($direct !== null && (! is_string($direct) || trim($direct) !== '')) {
            $candidates[] = $direct;
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
            $candidates[] = $mv;
        }

        $nested = self::firstScalarMatchInTree($meta, $metadataKeys);
        if ($nested !== null && $nested !== '') {
            $candidates[] = $nested;
        }

        if ($column === 'date_of_birth') {
            $fromDocs = self::firstScalarMatchInDocumentRows($employee->id_documents_json ?? null, $metadataKeys);
            if ($fromDocs !== null && $fromDocs !== '') {
                $candidates[] = $fromDocs;
            }
        }

        foreach ($candidates as $candidate) {
            if (self::parseStoredDateWithFormat($candidate) !== null) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== null && (! is_string($candidate) || trim($candidate) !== '')) {
                return $candidate;
            }
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
                    $parsedOld = self::parseStoredDateWithFormat($ov);
                    if ($parsedOld !== null) {
                        return $parsedOld['iso'];
                    }
                }
                // Flashed empty or unparseable old input must not hide a persisted DB date
            }
        }

        $parsed = self::parseStoredDateWithFormat($fromModel);
        if ($parsed !== null) {
            return $parsed['iso'];
        }

        $fromDb = self::databaseColumnValue($employee, $column);
        $parsedDb = self::parseStoredDateWithFormat($fromDb);

        return $parsedDb['iso'] ?? '';
    }

    /**
     * PHP date() format token for round-tripping admin saves (hidden input per date field).
     *
     * @param  list<string>  $metadataKeys
     */
    public static function adminDateStorageFormat(Request $request, Employee $employee, string $column, array $metadataKeys): string
    {
        $formatKey = $column.'_storage_format';
        $errors = $request->session()->get('errors');
        if ($errors instanceof ViewErrorBag && $errors->any()) {
            $old = $request->session()->getOldInput();
            if (is_array($old) && array_key_exists($formatKey, $old)) {
                $candidate = trim((string) $old[$formatKey]);
                if ($candidate !== '' && in_array($candidate, self::allowedStorageDateFormats(), true)) {
                    return $candidate;
                }
            }
        }

        $fromModel = self::employeeRawDateValue($employee, $column, $metadataKeys);
        $parsed = self::parseStoredDateWithFormat($fromModel);

        return $parsed['format'] ?? 'Y-m-d';
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
                $parsed = self::parseStoredDateWithFormat($arr[$col]);
                if ($parsed === null) {
                    continue;
                }
                if (($inputs[$col] ?? '') === '') {
                    $inputs[$col] = $parsed['iso'];
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
     * Fills still-empty storage-format hints from the raw tenant row.
     *
     * @param  array<string, string>  $formats
     * @return array<string, string>
     */
    public static function mergeRegistrationDateFormatsFromDatabase(string $connection, string $publicId, array $formats): array
    {
        try {
            $row = DB::connection($connection)->table('employees')->where('public_id', $publicId)->first();
            if ($row === null) {
                return $formats;
            }
            $arr = (array) $row;
            foreach (array_keys(self::adminProfileDateMetadataKeys()) as $col) {
                if (! array_key_exists($col, $arr) || ($formats[$col] ?? '') !== '') {
                    continue;
                }
                $parsed = self::parseStoredDateWithFormat($arr[$col]);
                if ($parsed !== null) {
                    $formats[$col] = $parsed['format'];
                }
            }

            return $formats;
        } catch (\Throwable) {
            return $formats;
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
     * @return list<string>
     */
    public static function allowedStorageDateFormats(): array
    {
        return [
            'Y-m-d',
            'Y/m/d',
            'm/d/Y',
            'n/j/Y',
            'm-d-Y',
            'n-j-Y',
            'd/m/Y',
            'j/n/Y',
            'd-m-Y',
            'j-n-Y',
            'd.m.Y',
            'Ymd',
            'd/m/y',
            'm-d-y',
            'n-j-y',
            'd-m-y',
            'j-n-y',
        ];
    }

    /**
     * Parse a stored registration date and remember its display/storage format for admin round-trip.
     *
     * @return array{iso: string, format: string}|null
     */
    public static function parseStoredDateWithFormat(mixed $value): ?array
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
            $tz = DisplayTimezone::name();
            if (abs($f) > 31_536_000) {
                try {
                    $iso = Carbon::createFromTimestamp((int) $f)->timezone($tz)->format('Y-m-d');

                    return ['iso' => $iso, 'format' => 'Y-m-d'];
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
                    return ['iso' => sprintf('%04d-%02d-%02d', $y, $m, $d), 'format' => 'Y-m-d'];
                }
                if ($y > 0 && $m >= 0 && $m <= 11 && $d > 0) {
                    $m1 = $m + 1;
                    if (checkdate($m1, $d, $y)) {
                        return ['iso' => sprintf('%04d-%02d-%02d', $y, $m1, $d), 'format' => 'Y-m-d'];
                    }
                }
            }
        }

        if ($value instanceof CarbonInterface) {
            return ['iso' => $value->format('Y-m-d'), 'format' => 'Y-m-d'];
        }
        if ($value instanceof \DateTimeInterface) {
            try {
                $iso = Carbon::parse($value)->format('Y-m-d');

                return ['iso' => $iso, 'format' => 'Y-m-d'];
            } catch (\Throwable) {
                return null;
            }
        }

        $s = self::normalizeDateString((string) $value);
        if ($s === '') {
            return null;
        }

        if (preg_match('#^(\d{1,2}[-/.]\d{1,2}[-/.]\d{2,4})(?:\s|T).+#', $s, $datePrefix)) {
            $prefixParsed = self::parseStoredDateWithFormat($datePrefix[1]);
            if ($prefixParsed !== null) {
                return $prefixParsed;
            }
        }

        $strictFormat = self::detectStorageDateFormat($s);
        if ($strictFormat !== null) {
            try {
                $parsed = Carbon::createFromFormat('!'.$strictFormat, $s);
                if ($parsed !== false) {
                    return ['iso' => $parsed->format('Y-m-d'), 'format' => $strictFormat];
                }
            } catch (\Throwable) {
            }
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            try {
                $day = Carbon::createFromFormat('Y-m-d', $m[1]);
                if ($day !== false && $day->format('Y-m-d') === $m[1]) {
                    return ['iso' => $m[1], 'format' => 'Y-m-d'];
                }
            } catch (\Throwable) {
            }
        }

        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            try {
                $day = Carbon::createFromFormat('Y-m-d', $m[1]);
                if ($day !== false && $day->format('Y-m-d') === $m[1]) {
                    return ['iso' => $m[1], 'format' => 'Y-m-d'];
                }
            } catch (\Throwable) {
            }
        }

        if (preg_match('#^(\d{1,2})([-/.])(\d{1,2})\2(\d{2,4})(?:\D|$)#', $s, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[3];
            $year = (int) $m[4];
            if ($year >= 0 && $year < 100) {
                $year += $year >= 70 ? 1900 : 2000;
            }
            if ($year >= 1000 && $year <= 9999) {
                $usValid = $a >= 1 && $a <= 12 && $b >= 1 && $b <= 31 && checkdate($a, $b, $year);
                $euValid = $b >= 1 && $b <= 12 && $a >= 1 && $a <= 31 && checkdate($b, $a, $year);
                if ($usValid && ! $euValid) {
                    return [
                        'iso' => sprintf('%04d-%02d-%02d', $year, $a, $b),
                        'format' => self::inferNumericDateFormat($s, true),
                    ];
                }
                if ($euValid && ! $usValid) {
                    return [
                        'iso' => sprintf('%04d-%02d-%02d', $year, $b, $a),
                        'format' => self::inferNumericDateFormat($s, false),
                    ];
                }
                if ($usValid) {
                    return [
                        'iso' => sprintf('%04d-%02d-%02d', $year, $a, $b),
                        'format' => self::inferNumericDateFormat($s, true),
                    ];
                }
                if ($euValid) {
                    return [
                        'iso' => sprintf('%04d-%02d-%02d', $year, $b, $a),
                        'format' => self::inferNumericDateFormat($s, false),
                    ];
                }
            }
        }

        if (preg_match('/^\d{8}$/', $s)) {
            try {
                $parsed = Carbon::createFromFormat('Ymd', $s);

                return ['iso' => $parsed->format('Y-m-d'), 'format' => 'Ymd'];
            } catch (\Throwable) {
            }
        }

        if (preg_match('/^\d{10,13}$/', $s)) {
            $ts = strlen($s) >= 13
                ? (int) floor((float) $s / 1000)
                : (int) $s;
            if ($ts > 0) {
                try {
                    $iso = Carbon::createFromTimestamp($ts)->timezone(DisplayTimezone::name())->format('Y-m-d');

                    return ['iso' => $iso, 'format' => 'Y-m-d'];
                } catch (\Throwable) {
                }
            }
        }

        try {
            $iso = Carbon::parse($s)->format('Y-m-d');

            return ['iso' => $iso, 'format' => 'Y-m-d'];
        } catch (\Throwable) {
        }

        foreach (self::allowedStorageDateFormats() as $fmt) {
            try {
                $parsed = Carbon::createFromFormat($fmt, $s);
                if ($parsed !== false) {
                    return ['iso' => $parsed->format('Y-m-d'), 'format' => $fmt];
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * Normalize a stored or submitted date to Y-m-d, or null if empty / unparseable.
     */
    public static function toNullableIsoDate(mixed $value): ?string
    {
        return self::parseStoredDateWithFormat($value)['iso'] ?? null;
    }

    /**
     * Convert an HTML date input value (Y-m-d) back to the employee's original storage format.
     */
    public static function isoDateToStorageFormat(string $iso, string $storageFormat): ?string
    {
        $format = trim($storageFormat);
        if ($format === '' || $format === 'Y-m-d') {
            return $iso;
        }
        if (! in_array($format, self::allowedStorageDateFormats(), true)) {
            return $iso;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $iso)->format($format);
        } catch (\Throwable) {
            return $iso;
        }
    }

    /**
     * Persist admin date field: accept picker ISO, store using remembered format.
     */
    public static function persistAdminDateField(mixed $submitted, ?string $storageFormat): ?string
    {
        $iso = self::toNullableIsoDate($submitted);
        if ($iso === null) {
            return null;
        }

        return self::isoDateToStorageFormat($iso, $storageFormat ?? 'Y-m-d');
    }

    private static function normalizeDateString(string $value): string
    {
        $s = trim($value);
        if ($s === '') {
            return '';
        }

        $s = str_replace("\xC2\xA0", ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        if (
            strlen($s) >= 2
            && (($s[0] === '"' && $s[strlen($s) - 1] === '"') || ($s[0] === "'" && $s[strlen($s) - 1] === "'"))
        ) {
            $s = trim(substr($s, 1, -1));
        }

        return self::compactSpacedDateString(trim($s));
    }

    /**
     * DB/mobile dates sometimes store spaces around separators ("03 / 15 / 2026") or between parts.
     */
    private static function compactSpacedDateString(string $s): string
    {
        if ($s === '') {
            return '';
        }

        // "03 / 15 / 2026", "2026 - 03 - 15", "03. 15. 2026" → compact separators
        $s = preg_replace('/\s*([\-\/\.])\s*/u', '$1', $s) ?? $s;

        // "03 15 2026" or "2026 03 15" (spaces only, no separator)
        if (preg_match('/^(\d{1,4})\s+(\d{1,2})\s+(\d{1,4})$/', $s, $m)) {
            return $m[1].'/'.$m[2].'/'.$m[3];
        }

        // Any remaining internal spaces (e.g. typo "03/ 15/ 2026" after partial cleanup)
        if (preg_match('/^\d/', $s) && preg_match('/\d$/', $s) && str_contains($s, ' ')) {
            $s = preg_replace('/\s+/u', '', $s) ?? $s;
        }

        return trim($s);
    }

    private static function detectStorageDateFormat(string $s): ?string
    {
        foreach (self::allowedStorageDateFormats() as $fmt) {
            try {
                $parsed = Carbon::createFromFormat('!'.$fmt, $s);
                if ($parsed !== false && $parsed->format($fmt) === $s) {
                    return $fmt;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private static function inferNumericDateFormat(string $s, bool $monthFirst): string
    {
        if (! preg_match('#^(\d{1,2})([-/.])(\d{1,2})\2(\d{4})\b#', $s, $m)) {
            return $monthFirst ? 'm/d/Y' : 'd/m/Y';
        }
        $sep = $m[2];
        $first = $m[1];
        $second = $m[3];
        if ($monthFirst) {
            $monthToken = strlen($first) >= 2 ? 'm' : 'n';
            $dayToken = strlen($second) >= 2 ? 'd' : 'j';

            return $monthToken.$sep.$dayToken.$sep.'Y';
        }
        $dayToken = strlen($first) >= 2 ? 'd' : 'j';
        $monthToken = strlen($second) >= 2 ? 'm' : 'n';

        return $dayToken.$sep.$monthToken.$sep.'Y';
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
     * AU-style profile line (d/m/Y) for expiry fields in ID & checks summaries.
     */
    public static function formatProfileDateLine(mixed $value): ?string
    {
        $iso = self::toNullableIsoDate($value);
        if ($iso !== null) {
            try {
                return Carbon::createFromFormat('!Y-m-d', $iso)->format('d/m/Y');
            } catch (\Throwable) {
                return $iso;
            }
        }
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return null;
    }

    /**
     * Expiry from the first insurance row whose type matches $typeLabel (value or picklist label).
     *
     * @param  iterable<int, object{value: string, label?: string|null}>|null  $picklistItems
     */
    public static function insuranceExpiryForType(?array $rows, string $typeLabel, ?iterable $picklistItems = null): ?string
    {
        if ($rows === null || $rows === []) {
            return null;
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = self::pickDocumentTitle($row);
            if ($type === '' || ! self::documentTypeMatches($type, $typeLabel, $picklistItems)) {
                continue;
            }
            $expiry = self::expiryFromDocumentRow($row);
            if ($expiry !== null && $expiry !== '') {
                return $expiry;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function expiryFromDocumentRow(array $row): ?string
    {
        foreach (['expiry', 'expiryDate', 'expiry_date', 'documentExpiry', 'document_expiry', 'expirationDate', 'expiration_date'] as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $display = self::formatProfileDateLine($row[$key]);
            if ($display !== null && $display !== '') {
                return $display;
            }
        }

        return null;
    }

    /**
     * Raw expiry value from a licence / insurance JSON row (before formatting).
     *
     * @param  array<string, mixed>  $row
     */
    public static function expiryRawFromDocumentRow(array $row): mixed
    {
        foreach (['expiry', 'expiryDate', 'expiry_date', 'documentExpiry', 'document_expiry', 'expirationDate', 'expiration_date'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * Y-m-d for HTML date inputs on licence / insurance JSON rows.
     *
     * @param  array<string, mixed>  $row
     */
    public static function documentRowExpiryInputValue(array $row): string
    {
        return self::toHtmlDateInput(self::expiryRawFromDocumentRow($row));
    }

    /**
     * Normalize expiry on each document row to ISO Y-m-d (mobile registration + admin saves).
     *
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return array<int, array<string, mixed>>|null
     */
    public static function normalizeDocumentJsonExpiryRows(?array $rows): ?array
    {
        if ($rows === null) {
            return null;
        }

        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $iso = self::toNullableIsoDate(self::expiryRawFromDocumentRow($row));
            if ($iso === null) {
                continue;
            }
            $rows[$i]['expiry'] = $iso;
            $rows[$i]['expiry_date'] = $iso;
        }

        return $rows;
    }

    /**
     * Human-readable summary line from structured licence / insurance JSON rows.
     *
     * @param  array<int, array<string, mixed>>|null  $rows
     */
    public static function rebuildDocumentRowsSummary(?array $rows): ?string
    {
        if ($rows === null || $rows === []) {
            return null;
        }

        $parts = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = self::pickDocumentTitle($row);
            if ($type === '') {
                continue;
            }
            $iso = self::toNullableIsoDate(self::expiryRawFromDocumentRow($row));
            $parts[] = $iso !== null ? $type.' (exp. '.$iso.')' : $type;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @param  iterable<int, object{value: string, label?: string|null}>|null  $picklistItems
     */
    public static function picklistLabel(?string $storedValue, ?iterable $picklistItems): ?string
    {
        if ($storedValue === null || trim($storedValue) === '') {
            return null;
        }
        if ($picklistItems === null) {
            return trim($storedValue);
        }
        $matched = self::matchPicklistValue(trim($storedValue), $picklistItems);
        foreach ($picklistItems as $item) {
            if ((string) ($item->value ?? '') === $matched) {
                $label = trim((string) ($item->label ?? ''));
                if ($label !== '') {
                    return $label;
                }

                return $matched;
            }
        }

        return trim($storedValue);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function metaLinesForDocumentRow(array $row): array
    {
        $skip = ['storage_path', 'documentKey', 'id', 'uri', 'localUri', 'expiry', 'expiryDate', 'expiry_date', 'documentExpiry', 'document_expiry', 'expirationDate', 'expiration_date', 'imageUploaded', 'image_uploaded'];
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
            $expiryInput = self::documentRowExpiryInputValue($row);

            $out[] = [
                'title' => $title,
                'display_label' => self::pickDocumentTitle($row) ?: '',
                'subtitle' => $subtitle,
                'meta' => $meta,
                'storage_path' => $path,
                'row_key' => $key !== '' ? $key : null,
                'expiry_input' => $expiryInput,
                'expiry_display' => self::expiryFromDocumentRow($row),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function pickDocumentTitle(array $row): string
    {
        foreach (['documentType', 'document_type', 'idType', 'id_type', 'type', 'name', 'title', 'label'] as $k) {
            if (! empty($row[$k]) && is_scalar($row[$k])) {
                return trim((string) $row[$k]);
            }
        }

        return '';
    }

    /**
     * @param  iterable<int, object{value: string, label?: string|null}>|null  $picklistItems
     */
    private static function documentTypeMatches(string $candidate, string $needle, ?iterable $picklistItems = null): bool
    {
        $c = trim($candidate);
        $n = trim($needle);
        if ($c === '' || $n === '') {
            return false;
        }
        if (strcasecmp($c, $n) === 0) {
            return true;
        }
        if ($picklistItems !== null) {
            $matchedCandidate = self::matchPicklistValue($c, $picklistItems);
            $matchedNeedle = self::matchPicklistValue($n, $picklistItems);
            if ($matchedCandidate !== '' && $matchedNeedle !== '' && strcasecmp($matchedCandidate, $matchedNeedle) === 0) {
                return true;
            }
        }

        return str_contains(strtolower($c), strtolower($n));
    }

    /**
     * Match a stored document type string to a picklist value (value or label).
     *
     * @param  iterable<int, object{value: string, label?: string|null}>  $items
     */
    public static function matchPicklistValue(string $candidate, iterable $items): string
    {
        $c = trim($candidate);
        if ($c === '') {
            return '';
        }
        foreach ($items as $item) {
            $value = (string) ($item->value ?? '');
            $label = (string) ($item->label ?? $value);
            if (strcasecmp($value, $c) === 0 || strcasecmp($label, $c) === 0) {
                return $value;
            }
        }

        return $c;
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

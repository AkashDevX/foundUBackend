<?php

namespace App\Support;

use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Profile documents that carry an expiry date (visa, police check, fit to work,
 * vehicle registration, licences, insurances, and ID rows).
 *
 * A document is due when its expiry is within {@see self::WINDOW_DAYS} days or
 * already past. It stays due until a renewed file is stored and the new expiry
 * is more than that window away.
 */
final class DocumentRenewal
{
    public const WINDOW_DAYS = 30;

    private const DISK = 'employee_registration';

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf', 'doc', 'docx'];

    /**
     * @var array<string, array{label: string, expiry: string, aliases: list<string>, path: string, uploaded: string|null}>
     */
    private const SCALAR_DOCUMENTS = [
        'visa' => [
            'label' => 'Visa',
            'expiry' => 'visa_expiry',
            'aliases' => ['visaExpiry', 'visa_expiry'],
            'path' => 'visa_document_path',
            'uploaded' => null,
        ],
        'police_check' => [
            'label' => 'Police check',
            'expiry' => 'police_check_expiry',
            'aliases' => ['policeCheckExpiry', 'police_check_expiry'],
            'path' => 'police_check_path',
            'uploaded' => 'police_check_uploaded',
        ],
        'fit_to_work' => [
            'label' => 'Fit to work',
            'expiry' => 'fit_to_work_expiry',
            'aliases' => ['fitToWorkExpiry', 'fit_to_work_expiry'],
            'path' => 'fit_to_work_path',
            'uploaded' => 'fit_to_work_uploaded',
        ],
        'vehicle_registration' => [
            'label' => 'Vehicle registration',
            'expiry' => 'vehicle_expiry',
            'aliases' => ['vehicleExpiry', 'vehicle_expiry'],
            'path' => 'vehicle_registration_path',
            'uploaded' => null,
        ],
    ];

    /**
     * @var array<string, array{attribute: string, idField: string, summary: string, fallback: string}>
     */
    private const JSON_GROUPS = [
        'licence' => [
            'attribute' => 'licences_json',
            'idField' => 'id',
            'summary' => 'licences_summary',
            'fallback' => 'Licence',
        ],
        'insurance' => [
            'attribute' => 'insurances_json',
            'idField' => 'id',
            'summary' => 'insurances_summary',
            'fallback' => 'Insurance',
        ],
        'id_document' => [
            'attribute' => 'id_documents_json',
            'idField' => 'documentKey',
            'summary' => 'id_documents_summary',
            'fallback' => 'ID document',
        ],
    ];

    /**
     * @return list<array{key: string, label: string, expiry: string, days_until: int, status: string}>
     */
    public static function dueItems(Employee $employee, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? DisplayTimezone::now())->copy()->timezone(DisplayTimezone::name())->startOfDay();
        $items = [];

        foreach (self::SCALAR_DOCUMENTS as $key => $meta) {
            if ($key === 'visa' && ! RegistrationDisplay::requiresVisaDocument(
                is_string($employee->visa_status) ? $employee->visa_status : null,
            )) {
                continue;
            }

            $iso = RegistrationDisplay::toNullableIsoDate(
                RegistrationDisplay::employeeRawDateValue($employee, $meta['expiry'], $meta['aliases']),
            );
            $item = self::itemFromIso($key, $meta['label'], $iso, $today);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        foreach (self::JSON_GROUPS as $prefix => $meta) {
            $rows = $employee->{$meta['attribute']} ?? null;
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = self::rowId($row, $meta['idField'], (int) $index);
                $label = RegistrationDisplay::documentDisplayTitle($row);
                if ($label === '') {
                    $label = $meta['fallback'];
                }
                $iso = RegistrationDisplay::toNullableIsoDate(RegistrationDisplay::expiryRawFromDocumentRow($row));
                $item = self::itemFromIso($prefix.':'.$id, $label, $iso, $today);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @param  list<array{key: string, label: string, expiry: string, days_until: int, status: string}>  $items
     * @return array{title: string, body: string}
     */
    public static function reminderCopy(array $items): array
    {
        if ($items === []) {
            return ['title' => '', 'body' => ''];
        }

        $sentences = array_map(static fn (array $item): string => self::reminderSentence($item), $items);
        $body = implode(' ', $sentences)
            .' Open My profile to upload the renewed document and set the new expiry. This reminder repeats every day until you submit it.';

        if (count($items) === 1) {
            $item = $items[0];

            return [
                'title' => self::reminderTitle($item),
                'body' => $body,
            ];
        }

        return [
            'title' => 'Documents need renewing',
            'body' => $body,
        ];
    }

    /**
     * @param  array{key: string, label: string, expiry: string, days_until: int, status: string}  $item
     */
    private static function reminderSentence(array $item): string
    {
        $label = $item['label'];
        $days = (int) $item['days_until'];
        if ($days < 0 || $item['status'] === 'expired') {
            $ago = abs($days);

            return 'Your '.$label.' expired '.$ago.' '.self::dayWord($ago).' ago. Please renew it.';
        }
        if ($days === 0) {
            return 'Your '.$label.' expires today. Please renew it.';
        }

        return 'Your '.$label.' expires in '.$days.' '.self::dayWord($days).'. Please renew it.';
    }

    /**
     * @param  array{key: string, label: string, expiry: string, days_until: int, status: string}  $item
     */
    private static function reminderTitle(array $item): string
    {
        $label = $item['label'];
        $days = (int) $item['days_until'];
        if ($days < 0 || $item['status'] === 'expired') {
            $ago = abs($days);

            return 'Your '.$label.' expired '.$ago.' '.self::dayWord($ago).' ago';
        }
        if ($days === 0) {
            return 'Your '.$label.' expires today';
        }

        return 'Your '.$label.' expires in '.$days.' '.self::dayWord($days);
    }

    private static function dayWord(int $days): string
    {
        return abs($days) === 1 ? 'day' : 'days';
    }

    /**
     * @return array{ok: true, message: string, still_due: bool}|array{ok: false, message: string}
     */
    public static function apply(
        Employee $employee,
        string $companySlug,
        string $key,
        string $expiry,
        UploadedFile $file,
        ?CarbonInterface $today = null,
    ): array {
        $key = trim($key);
        if ($key === '') {
            return ['ok' => false, 'message' => 'Choose which document to renew.'];
        }

        if (! $file->isValid()) {
            return ['ok' => false, 'message' => 'That file could not be read. Choose it again.'];
        }

        if (! self::fileIsAllowed($file)) {
            return ['ok' => false, 'message' => 'Use a JPG, PNG, PDF, DOC, or DOCX file.'];
        }

        $iso = RegistrationDisplay::toNullableIsoDate($expiry);
        if ($iso === null) {
            return ['ok' => false, 'message' => 'Set a valid expiry date.'];
        }

        $today = ($today ?? DisplayTimezone::now())->copy()->timezone(DisplayTimezone::name())->startOfDay();
        $expiryDate = self::parseIso($iso);
        if ($expiryDate === null) {
            return ['ok' => false, 'message' => 'Set a valid expiry date.'];
        }
        if ($expiryDate->lte($today)) {
            return ['ok' => false, 'message' => 'The new expiry date must be after today.'];
        }

        $prefix = trim($companySlug, '/').'/'.($employee->public_id ?: (string) $employee->id);
        $stored = $file->store($prefix, self::DISK);
        if (! is_string($stored) || $stored === '') {
            return ['ok' => false, 'message' => 'The renewed document could not be saved. Try again.'];
        }

        $applied = self::writeRenewal($employee, $key, $iso, $stored);
        if (! $applied) {
            self::deleteStoredPath($stored);

            return ['ok' => false, 'message' => 'That document is no longer on your profile.'];
        }

        $employee->save();

        $stillDue = self::daysUntil($today, $expiryDate) <= self::WINDOW_DAYS;

        return [
            'ok' => true,
            'still_due' => $stillDue,
            'message' => $stillDue
                ? 'Document saved. Daily reminders continue until the expiry is more than 30 days away.'
                : 'Document saved. Daily reminders for this document have stopped.',
        ];
    }

    public static function vehicleRegistrationPath(Employee $employee): ?string
    {
        $meta = $employee->profile_metadata;
        if (! is_array($meta)) {
            return null;
        }
        $path = $meta['vehicle_registration_path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * @return array{key: string, label: string, expiry: string, days_until: int, status: string}|null
     */
    private static function itemFromIso(string $key, string $label, ?string $iso, CarbonInterface $today): ?array
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        $expiry = self::parseIso($iso);
        if ($expiry === null) {
            return null;
        }
        $daysUntil = self::daysUntil($today, $expiry);
        if ($daysUntil > self::WINDOW_DAYS) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'expiry' => $iso,
            'days_until' => $daysUntil,
            'status' => $daysUntil < 0 ? 'expired' : 'expiring',
        ];
    }

    private static function daysUntil(CarbonInterface $today, CarbonInterface $expiry): int
    {
        $start = $today->copy()->timezone(DisplayTimezone::name())->startOfDay()->getTimestamp();
        $end = $expiry->copy()->timezone(DisplayTimezone::name())->startOfDay()->getTimestamp();

        return (int) round(($end - $start) / 86400);
    }

    private static function parseIso(string $iso): ?CarbonInterface
    {
        try {
            return Carbon::createFromFormat('!Y-m-d', $iso, DisplayTimezone::name())->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function fileIsAllowed(UploadedFile $file): bool
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return true;
        }

        $mime = strtolower((string) $file->getMimeType());

        return in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/heic',
            'image/heif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], true);
    }

    private static function writeRenewal(Employee $employee, string $key, string $iso, string $stored): bool
    {
        if (isset(self::SCALAR_DOCUMENTS[$key])) {
            return self::writeScalar($employee, $key, $iso, $stored);
        }

        foreach (self::JSON_GROUPS as $prefix => $meta) {
            $needle = $prefix.':';
            if (! str_starts_with($key, $needle)) {
                continue;
            }

            return self::writeJsonRow($employee, $meta, substr($key, strlen($needle)), $iso, $stored);
        }

        return false;
    }

    private static function writeScalar(Employee $employee, string $key, string $iso, string $stored): bool
    {
        $meta = self::SCALAR_DOCUMENTS[$key];
        if ($key === 'visa' && ! RegistrationDisplay::requiresVisaDocument(
            is_string($employee->visa_status) ? $employee->visa_status : null,
        )) {
            return false;
        }

        if ($key === 'vehicle_registration') {
            $previous = self::vehicleRegistrationPath($employee);
            self::deleteStoredPath($previous);
            $metadata = is_array($employee->profile_metadata) ? $employee->profile_metadata : [];
            $metadata['vehicle_registration_path'] = $stored;
            $employee->profile_metadata = $metadata;
        } else {
            $previous = $employee->{$meta['path']} ?? null;
            self::deleteStoredPath(is_string($previous) ? $previous : null);
            $employee->{$meta['path']} = $stored;
        }

        $employee->{$meta['expiry']} = $iso;
        if ($meta['uploaded'] !== null) {
            $employee->{$meta['uploaded']} = 'Yes';
        }

        return true;
    }

    /**
     * @param  array{attribute: string, idField: string, summary: string, fallback: string}  $meta
     */
    private static function writeJsonRow(Employee $employee, array $meta, string $rowId, string $iso, string $stored): bool
    {
        $rows = $employee->{$meta['attribute']} ?? null;
        if (! is_array($rows)) {
            return false;
        }

        $found = false;
        foreach ($rows as $index => &$row) {
            if (! is_array($row)) {
                continue;
            }
            if (self::rowId($row, $meta['idField'], (int) $index) !== $rowId) {
                continue;
            }
            $previous = $row['storage_path'] ?? null;
            self::deleteStoredPath(is_string($previous) ? $previous : null);
            unset($row['uri'], $row['localUri']);
            $row['storage_path'] = $stored;
            $row['expiry'] = $iso;
            $row['expiry_date'] = $iso;
            $row['imageUploaded'] = true;
            $found = true;
            break;
        }
        unset($row);

        if (! $found) {
            return false;
        }

        $employee->forceFill([
            $meta['attribute'] => json_decode(json_encode($rows, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
            $meta['summary'] => RegistrationDisplay::rebuildDocumentRowsSummary($rows),
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowId(array $row, string $idField, int $index): string
    {
        $fields = $idField === 'documentKey'
            ? ['documentKey', 'document_key', 'id']
            : [$idField];

        foreach ($fields as $field) {
            $raw = $row[$field] ?? null;
            if (is_scalar($raw) && trim((string) $raw) !== '') {
                return trim((string) $raw);
            }
        }

        return 'index:'.$index;
    }

    private static function deleteStoredPath(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }
}

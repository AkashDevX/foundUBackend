<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Employee;
use App\Models\TrainingAssignment;
use App\Models\TrainingCertificate;
use App\Models\TrainingModule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates of completion. Every passed training issues one.
 */
final class TrainingCertificates
{
    /** @var array<string, bool> */
    private static array $ready = [];

    /** @var array<string, string> */
    private static array $companyBySlug = [];

    public static function shouldIssue(?bool $passed): bool
    {
        return $passed === true;
    }

    public static function completionDate(?CarbonInterface $submittedAt): ?Carbon
    {
        if ($submittedAt === null) {
            return null;
        }

        return $submittedAt->copy()->timezone(DisplayTimezone::name())->startOfDay();
    }

    public static function refresherDate(?CarbonInterface $completedOn, ?int $validityMonths): ?Carbon
    {
        if ($completedOn === null || $validityMonths === null || $validityMonths < 1) {
            return null;
        }

        return $completedOn->copy()->timezone(DisplayTimezone::name())->startOfDay()->addMonths($validityMonths);
    }

    public static function makeReference(?CarbonInterface $completedOn = null): string
    {
        $year = ($completedOn ?? DisplayTimezone::now())->format('Y');
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $suffix = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < 6; $i++) {
            $suffix .= $alphabet[random_int(0, $max)];
        }

        return 'TC-'.$year.'-'.$suffix;
    }

    /**
     * @return array{
     *     reference_number: string,
     *     employee_name: string,
     *     training_name: string,
     *     company_name: string,
     *     completed_on: string|null,
     *     completed_on_label: string|null,
     *     expires_on: string|null,
     *     expires_on_label: string|null,
     *     signature: array{width: int, height: int, strokes: list<list<array{x: float, y: float}>>}|null
     * }
     */
    public static function present(TrainingCertificate $certificate): array
    {
        return [
            'reference_number' => $certificate->reference_number,
            'employee_name' => $certificate->employee_name,
            'training_name' => $certificate->training_name,
            'company_name' => $certificate->company_name,
            'completed_on' => $certificate->completed_on?->toDateString(),
            'completed_on_label' => $certificate->completed_on?->format('j F Y'),
            'expires_on' => $certificate->expires_on?->toDateString(),
            'expires_on_label' => $certificate->expires_on?->format('j F Y'),
            'signature' => $certificate->signatureDrawing(),
        ];
    }

    /**
     * Same stroke record used by incident reports.
     *
     * @return array{signed: bool, width: int, height: int, strokes: list<list<array{x: float, y: float}>>}
     */
    public static function normalizeSignature(mixed $raw): array
    {
        $empty = ['signed' => false, 'width' => 320, 'height' => 160, 'strokes' => []];
        if (! is_array($raw)) {
            return $empty;
        }

        $width = max(1, min((int) ($raw['width'] ?? 320), 2000));
        $height = max(1, min((int) ($raw['height'] ?? 160), 2000));
        $source = $raw['strokes'] ?? [];
        if (! is_array($source)) {
            return $empty;
        }

        $strokes = [];
        foreach (array_slice($source, 0, 80) as $stroke) {
            if (! is_array($stroke)) {
                continue;
            }
            $points = [];
            foreach (array_slice($stroke, 0, 500) as $point) {
                if (! is_array($point) || ! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)) {
                    continue;
                }
                $points[] = [
                    'x' => round(max(0, min((float) $point['x'], $width)), 1),
                    'y' => round(max(0, min((float) $point['y'], $height)), 1),
                ];
            }
            if ($points !== []) {
                $strokes[] = $points;
            }
        }

        return [
            'signed' => $strokes !== [],
            'width' => $width,
            'height' => $height,
            'strokes' => $strokes,
        ];
    }

    public static function ready(string $connection): bool
    {
        if (! array_key_exists($connection, self::$ready)) {
            self::$ready[$connection] = Schema::connection($connection)->hasTable('training_certificates')
                && Schema::connection($connection)->hasColumn('training_modules', 'issues_certificate');
        }

        return self::$ready[$connection];
    }

    /**
     * Official name of the organization the employee is signed in to.
     * Each organization (Blue Green, Construct Concepts, Aid and Able, and any later one)
     * has its own name on the company registry.
     */
    public static function organizationName(?\Illuminate\Http\Request $request = null): ?string
    {
        $name = trim((string) (CurrentTenant::company($request)?->name ?? ''));

        return $name !== '' ? $name : null;
    }

    public static function companyName(?string $preferred, ?Employee $employee): string
    {
        $name = trim((string) $preferred);
        if ($name === '') {
            $name = (string) (self::organizationName() ?? '');
        }
        if ($name !== '') {
            return $name;
        }

        $fromEmployee = trim((string) ($employee?->company_display_name ?? ''));
        if ($fromEmployee !== '') {
            return $fromEmployee;
        }

        $slug = trim((string) ($employee?->registration_company_slug ?? ''));
        if ($slug !== '') {
            if (! array_key_exists($slug, self::$companyBySlug)) {
                $lookedUp = Company::query()->where('slug', $slug)->value('name');
                self::$companyBySlug[$slug] = is_string($lookedUp) ? trim($lookedUp) : '';
            }
            if (self::$companyBySlug[$slug] !== '') {
                return self::$companyBySlug[$slug];
            }
        }

        return 'Company';
    }

    /**
     * @param  array{signed: bool, width: int, height: int, strokes: list<list<array{x: float, y: float}>>}|null  $signature
     */
    public static function issueIfRequired(TrainingAssignment $assignment, ?string $companyName = null, ?array $signature = null): ?TrainingCertificate
    {
        $connection = $assignment->getConnectionName();
        if (! self::ready($connection)) {
            return null;
        }

        $assignment->loadMissing(['module', 'attempt', 'employee', 'certificate']);
        $module = $assignment->module;
        $attempt = $assignment->attempt;
        $passed = $attempt?->passed === null ? null : (bool) $attempt->passed;
        if (
            $module === null
            || $attempt === null
            || $attempt->submitted_at === null
            || ! self::shouldIssue($passed)
        ) {
            return $assignment->certificate;
        }

        $completedOn = self::completionDate($attempt->submitted_at);
        if ($completedOn === null) {
            return null;
        }

        $months = $module->certificate_validity_months !== null
            ? (int) $module->certificate_validity_months
            : null;
        $expiresOn = self::refresherDate($completedOn, $months);

        $employee = $assignment->employee;
        $resolvedCompany = mb_substr(self::companyName($companyName, $employee), 0, 200);

        $existing = $assignment->certificate;
        if ($existing !== null) {
            $nextExpiry = $expiresOn?->toDateString();
            $dirty = false;
            if ($existing->expires_on?->toDateString() !== $nextExpiry) {
                $existing->expires_on = $nextExpiry;
                $dirty = true;
            }
            if ($resolvedCompany !== '' && $resolvedCompany !== 'Company' && $existing->company_name !== $resolvedCompany) {
                $existing->company_name = $resolvedCompany;
                $dirty = true;
            }
            if (self::canStoreSignature($connection, $signature)) {
                $existing->fill(self::signatureAttributes($signature));
                $dirty = true;
            }
            if ($dirty) {
                $existing->save();
            }

            return $existing;
        }
        $payload = [
            'training_assignment_id' => $assignment->id,
            'training_attempt_id' => $attempt->id,
            'employee_id' => $assignment->employee_id,
            'employee_name' => self::employeeName($employee),
            'training_name' => mb_substr(trim((string) ($module->title ?: 'Training')), 0, 200),
            'company_name' => $resolvedCompany,
            'completed_on' => $completedOn->toDateString(),
            'expires_on' => $expiresOn?->toDateString(),
            ...self::signatureAttributes(self::canStoreSignature($connection, $signature) ? $signature : null),
        ];

        $created = null;
        for ($try = 0; $try < 5; $try++) {
            try {
                $created = TrainingCertificate::on($connection)->create([
                    ...$payload,
                    'reference_number' => self::makeReference($completedOn),
                ]);
                break;
            } catch (QueryException $e) {
                $already = TrainingCertificate::on($connection)
                    ->where('training_assignment_id', $assignment->id)
                    ->first();
                if ($already !== null) {
                    $assignment->setRelation('certificate', $already);

                    return $already;
                }
                if ($try === 4) {
                    throw $e;
                }
            }
        }

        if ($created !== null) {
            $assignment->setRelation('certificate', $created);
        }

        return $created;
    }

    public static function issueOutstanding(TrainingModule $module, ?string $companyName = null): int
    {
        $connection = $module->getConnectionName();
        if (! self::ready($connection)) {
            return 0;
        }

        $assignments = TrainingAssignment::on($connection)
            ->where('training_module_id', $module->id)
            ->with(['attempt', 'employee', 'certificate'])
            ->get();

        $created = 0;
        foreach ($assignments as $assignment) {
            $assignment->setRelation('module', $module);
            $hadCertificate = $assignment->certificate !== null;
            $certificate = self::issueIfRequired($assignment, $companyName);
            if ($certificate !== null && ! $hadCertificate) {
                $created++;
            }
        }

        return $created;
    }

    public static function revoke(TrainingAssignment $assignment): void
    {
        $connection = $assignment->getConnectionName();
        if (! self::ready($connection)) {
            return;
        }

        TrainingCertificate::on($connection)
            ->where('training_assignment_id', $assignment->id)
            ->delete();
        $assignment->unsetRelation('certificate');
    }

    /**
     * @param  array{signed: bool, width: int, height: int, strokes: list<list<array{x: float, y: float}>>}|null  $signature
     */
    private static function canStoreSignature(string $connection, ?array $signature): bool
    {
        return $signature !== null
            && ($signature['signed'] ?? false) === true
            && Schema::connection($connection)->hasColumn('training_certificates', 'signature_strokes');
    }

    /**
     * @param  array{signed: bool, width: int, height: int, strokes: list<list<array{x: float, y: float}>>}|null  $signature
     * @return array{signature_width?: int, signature_height?: int, signature_strokes?: list<list<array{x: float, y: float}>>}
     */
    private static function signatureAttributes(?array $signature): array
    {
        if ($signature === null || ($signature['signed'] ?? false) !== true) {
            return [];
        }

        return [
            'signature_width' => $signature['width'],
            'signature_height' => $signature['height'],
            'signature_strokes' => $signature['strokes'],
        ];
    }

    private static function employeeName(?Employee $employee): string
    {
        $name = trim((string) ($employee?->full_legal_name ?: $employee?->email ?: 'Employee'));

        return mb_substr($name !== '' ? $name : 'Employee', 0, 200);
    }
}

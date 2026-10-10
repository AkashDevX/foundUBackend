<?php

namespace App\Support;

use App\Models\Employee;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Styled PDF export of an employee profile for the admin portal.
 */
final class EmployeeProfilePdf
{
    public static function render(Employee $employee, string $companyName, ?Collection $picklists = null): string
    {
        $document = self::document($employee, $companyName, $picklists);
        $html = view('admin.employees.pdf', $document)->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function (int $pageNumber, int $pageCount, $canvas, $fontMetrics): void {
            $font = $fontMetrics->getFont('DejaVu Sans');
            $text = 'Page '.$pageNumber.' of '.$pageCount;
            $size = 8;
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $canvas->text(595.28 - 32 - $width, 841.89 - 22, $text, $font, $size, [0.42, 0.47, 0.55]);
        });

        $dompdf->addInfo('Title', $document['heading']);
        $dompdf->addInfo('Author', $companyName !== '' ? $companyName : 'CruLynk');
        $dompdf->addInfo('Subject', 'Employee profile');

        return $dompdf->output();
    }

    public static function filename(Employee $employee): string
    {
        $name = trim((string) ($employee->full_legal_name ?: $employee->email ?: 'employee'));
        $name = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-');
        if ($name === '') {
            $name = 'employee';
        }

        $code = trim((string) ($employee->employee_code ?: ''));
        $suffix = $code !== ''
            ? trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $code), '-')
            : substr(str_replace('-', '', (string) $employee->public_id), 0, 8);

        return $suffix !== '' ? $name.'-'.$suffix.'.pdf' : $name.'.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(Employee $employee, string $companyName, ?Collection $picklists = null): array
    {
        $name = trim((string) ($employee->full_legal_name ?: $employee->email ?: 'Employee'));
        $status = trim((string) ($employee->employment_status ?: ''));
        $code = trim((string) ($employee->employee_code ?: ''));

        return [
            'company' => $companyName !== '' ? $companyName : 'Organization',
            'logo' => self::logoImage(),
            'photo' => self::profilePhoto($employee),
            'heading' => 'Employee profile '.$name,
            'name' => $name,
            'code' => $code !== '' ? $code : 'Not assigned',
            'public_id' => trim((string) ($employee->public_id ?: '')),
            'email' => self::text($employee->email) !== '' ? self::text($employee->email) : 'Not recorded',
            'phone' => self::text($employee->phone) !== '' ? self::text($employee->phone) : 'Not recorded',
            'registered' => DisplayTimezone::formatDateTime($employee->created_at),
            'status_label' => $status !== '' ? ucfirst($status) : 'Unspecified',
            'tone' => self::statusTone($status),
            'generated' => DisplayTimezone::formatDateTime(DisplayTimezone::now()),
            'sections' => self::sections($employee, $picklists),
        ];
    }

    /**
     * @return list<array{title: string, rows: list<array{label: string, value: string}>}>
     */
    private static function sections(Employee $employee, ?Collection $picklists): array
    {
        $sections = [
            [
                'title' => 'IDENTITY & PERSONAL',
                'rows' => self::rows([
                    'Full legal name' => self::text($employee->full_legal_name),
                    'Date of birth' => self::dateLine($employee->date_of_birth),
                    'Sex' => self::text($employee->sex),
                    'Marital status' => self::choice($employee->marital_status, $picklists, 'marital_status'),
                    'Address' => self::text($employee->address),
                ]),
            ],
            [
                'title' => 'EMERGENCY CONTACT',
                'rows' => self::rows([
                    'Name' => self::text($employee->emergency_contact_name),
                    'Phone' => self::text($employee->emergency_contact_phone),
                    'Relationship' => self::text($employee->emergency_contact_relationship),
                ]),
            ],
            [
                'title' => 'WORK ELIGIBILITY & AVAILABILITY',
                'rows' => self::rows([
                    'Visa or residency status' => self::choice($employee->visa_status, $picklists, 'visa_status'),
                    'Unrestricted work rights in Australia' => self::yesNo($employee->unrestricted_work_rights),
                    'Visa expiry date' => self::dateLine($employee->visa_expiry),
                    'Visa document' => self::onFile($employee->visa_document_path),
                    'Preferred hours per week' => self::text($employee->hours_per_week),
                    'Weekly availability' => self::availability($employee),
                ]),
                'images' => self::collectImages([
                    ['label' => 'Visa document', 'path' => $employee->visa_document_path],
                ]),
            ],
            [
                'title' => 'ID & CHECKS',
                'rows' => self::rows([
                    'ID documents' => self::documentLines(RegistrationDisplay::idDocumentRows($employee->id_documents_json)),
                    'Police check expiry' => self::dateLine($employee->police_check_expiry),
                    'Police check' => self::onFile($employee->police_check_path, $employee->police_check_uploaded),
                    'Fit to work expiry' => self::dateLine($employee->fit_to_work_expiry),
                    'Fit to work' => self::onFile($employee->fit_to_work_path, $employee->fit_to_work_uploaded),
                ]),
                'images' => self::collectImages([
                    ...self::documentImageItems(RegistrationDisplay::idDocumentRows($employee->id_documents_json)),
                    ['label' => 'Police check', 'path' => $employee->police_check_path],
                    ['label' => 'Fit to work', 'path' => $employee->fit_to_work_path],
                ]),
            ],
            [
                'title' => 'LICENCES, INSURANCE & TRANSPORT',
                'rows' => self::rows([
                    'Licences' => self::documentLines(RegistrationDisplay::licenceRows($employee->licences_json)),
                    'Insurance' => self::documentLines(RegistrationDisplay::insuranceRows($employee->insurances_json)),
                    'Resume / CV' => self::onFile($employee->resume_path),
                    'Mode of transport' => self::choice($employee->mode_of_transport, $picklists, 'transport_mode'),
                    'Vehicle registration' => self::text($employee->vehicle_registration),
                    'Vehicle expiry' => self::dateLine($employee->vehicle_expiry),
                    'Vehicle insurance' => self::onFile($employee->vehicle_insurance_path, $employee->vehicle_insurance_uploaded),
                ]),
                'images' => self::collectImages([
                    ...self::documentImageItems(RegistrationDisplay::licenceRows($employee->licences_json)),
                    ...self::documentImageItems(RegistrationDisplay::insuranceRows($employee->insurances_json)),
                    ['label' => 'Vehicle registration', 'path' => $employee->vehicleRegistrationDocumentPath()],
                    ['label' => 'Vehicle insurance', 'path' => $employee->vehicle_insurance_path],
                    ['label' => 'Resume / CV', 'path' => $employee->resume_path],
                ]),
            ],
            [
                'title' => 'WORK ASSIGNMENT',
                'rows' => self::rows([
                    'Department' => self::department($employee),
                    'Work location' => self::location($employee),
                    'Job titles' => self::jobTitles($employee),
                    'Employee code' => self::text($employee->employee_code),
                    'Effective from' => self::dateLine($employee->assignment_effective_from),
                    'Assignment notes' => self::text($employee->assignment_notes),
                ]),
            ],
            [
                'title' => 'PAYROLL',
                'rows' => self::rows([
                    'Employment type' => self::text($employee->employment_type),
                    'Award level' => self::text($employee->award_level),
                    'Bank account name' => self::text($employee->bank_account_name),
                    'Bank name' => self::text($employee->bank_name),
                    'BSB' => self::text($employee->bank_branch_code),
                    'Account number' => self::maskedAccount($employee->bank_account_number),
                    'Allowances' => self::allowances($employee),
                    'Sick leave balance (hours)' => self::hours($employee->sick_leave_balance_hours),
                    'Annual leave balance (hours)' => self::hours($employee->annual_leave_balance_hours),
                    'Leave entitlements' => self::leaveEntitlements($employee),
                ]),
            ],
            [
                'title' => 'INDUCTION',
                'rows' => self::inductionRows($employee),
            ],
        ];

        return array_values(array_filter(
            $sections,
            static fn (array $section): bool => $section['rows'] !== [] || ($section['images'] ?? []) !== [],
        ));
    }

    /**
     * @param  array<string, string>  $pairs
     * @return list<array{label: string, value: string}>
     */
    private static function rows(array $pairs): array
    {
        $rows = [];
        foreach ($pairs as $label => $value) {
            $text = trim($value);
            if ($text === '') {
                continue;
            }
            $rows[] = ['label' => $label, 'value' => $text];
        }

        return $rows;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private static function inductionRows(Employee $employee): array
    {
        $card = InductionEligibility::adminCard($employee);
        if ($card === null) {
            return [];
        }

        $rows = self::rows([
            'Status' => (string) ($card['label'] ?? ''),
            'Passed' => $card['passed_at'] !== null ? DisplayTimezone::formatDateTime($card['passed_at']) : '',
            'Override by' => self::text($card['overridden_by'] ?? null),
            'Override on' => ($card['overridden_at'] ?? null) !== null ? DisplayTimezone::formatDateTime($card['overridden_at']) : '',
            'Override reason' => self::text($card['override_reason'] ?? null),
        ]);

        $attempts = [];
        foreach ($card['attempts'] ?? [] as $attempt) {
            if (! is_array($attempt)) {
                continue;
            }
            $percent = $attempt['percent'] !== null
                ? rtrim(rtrim(number_format((float) $attempt['percent'], 1), '0'), '.').'%'
                : '—';
            $attempts[] = 'Attempt '.$attempt['attempt_number'].': '.$percent
                .' ('.$attempt['score'].'/'.$attempt['max_score'].') — '
                .(($attempt['passed'] ?? false) ? 'passed' : 'not passed');
        }
        if ($attempts !== []) {
            $rows[] = ['label' => 'Attempts', 'value' => implode("\n", $attempts)];
        }

        return $rows;
    }

    private static function availability(Employee $employee): string
    {
        $grid = AdminWeeklyAvailability::mobileGridStateForEmployee(
            $employee->weekly_availability_json,
            $employee->weekly_availability_summary,
        );
        $lines = [];
        $any = false;
        foreach (AdminWeeklyAvailability::DAY_KEYS as $day) {
            $morning = (bool) ($grid[$day]['morning'] ?? false);
            $evening = (bool) ($grid[$day]['evening'] ?? false);
            if (! $morning && ! $evening) {
                continue;
            }
            $any = true;
            $slots = array_values(array_filter([
                $morning ? 'Morning' : null,
                $evening ? 'Evening' : null,
            ]));
            $lines[] = AdminWeeklyAvailability::FULL_DAY_LABELS[$day].' — '.implode(' and ', $slots);
        }
        if ($any) {
            return implode("\n", $lines);
        }

        return self::text($employee->weekly_availability_summary);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private static function documentLines(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $front = is_string($row['storage_path'] ?? null) && $row['storage_path'] !== '';
            $back = is_string($row['back_storage_path'] ?? null) && $row['back_storage_path'] !== '';
            $title = trim((string) ($row['title'] ?? 'Document'));
            $licence = RegistrationDisplay::isDriversLicenceType($title);
            if ($licence) {
                $file = $front && $back
                    ? 'Front and back on file'
                    : ($front ? 'Front on file, back missing' : ($back ? 'Back on file, front missing' : 'Not on file'));
            } else {
                $file = $front ? 'On file' : 'Not on file';
            }
            $parts = array_filter([
                $title !== '' ? $title : 'Document',
                trim((string) ($row['subtitle'] ?? '')) !== '' ? trim((string) $row['subtitle']) : null,
                trim((string) ($row['expiry_display'] ?? '')) !== '' ? 'Expires '.$row['expiry_display'] : null,
                $file,
            ]);
            $lines[] = implode(' · ', $parts);
        }

        return implode("\n", $lines);
    }

    private static function department(Employee $employee): string
    {
        if ($employee->relationLoaded('assignedDepartment') && $employee->assignedDepartment !== null) {
            $name = self::text($employee->assignedDepartment->name);
            $code = self::text($employee->assignedDepartment->code ?? null);

            return $code !== '' ? $name.' ('.$code.')' : $name;
        }

        return self::text($employee->department);
    }

    private static function location(Employee $employee): string
    {
        if (! $employee->relationLoaded('workLocation') || $employee->workLocation === null) {
            return '';
        }
        $name = self::text($employee->workLocation->name);
        $address = self::text($employee->workLocation->address ?? null);

        return $address !== '' ? $name."\n".$address : $name;
    }

    private static function jobTitles(Employee $employee): string
    {
        if ($employee->relationLoaded('jobTitles') && $employee->jobTitles->isNotEmpty()) {
            $names = [];
            foreach ($employee->jobTitles as $title) {
                $name = self::text($title->name);
                if ($name === '') {
                    continue;
                }
                $names[] = (bool) ($title->pivot->is_primary ?? false) ? $name.' (primary)' : $name;
            }
            if ($names !== []) {
                return implode(', ', $names);
            }
        }
        if ($employee->relationLoaded('assignedJobTitle') && $employee->assignedJobTitle !== null) {
            return self::text($employee->assignedJobTitle->name);
        }

        return self::text($employee->job_title);
    }

    private static function leaveEntitlements(Employee $employee): string
    {
        if (! $employee->relationLoaded('leaveEntitlements') || $employee->leaveEntitlements->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($employee->leaveEntitlements as $entitlement) {
            $type = $entitlement->relationLoaded('leaveType') ? self::text($entitlement->leaveType?->name) : '';
            if ($type === '') {
                $type = 'Leave';
            }
            $hours = self::hours($entitlement->entitlement_hours);
            $lines[] = $hours !== '' ? $type.' — '.$hours.' hours' : $type;
        }

        return implode("\n", $lines);
    }

    private static function allowances(Employee $employee): string
    {
        $rows = is_array($employee->payroll_allowances_json) ? $employee->payroll_allowances_json : [];
        $lines = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = self::text($row['name'] ?? null);
            if ($name === '') {
                continue;
            }
            $amount = $row['amount'] ?? null;
            $lines[] = is_numeric($amount) ? $name.' — $'.number_format((float) $amount, 2) : $name;
        }

        return implode("\n", $lines);
    }

    private static function choice(mixed $value, ?Collection $picklists, string $key): string
    {
        $stored = self::text($value);
        if ($stored === '' || $picklists === null) {
            return $stored;
        }

        return RegistrationDisplay::picklistLabel($stored, $picklists->get($key)) ?? $stored;
    }

    private static function onFile(mixed $path, mixed $declared = null): string
    {
        $hasFile = is_string($path) && trim($path) !== '';
        $answer = self::yesNo($declared);
        if ($hasFile) {
            return 'On file';
        }
        if ($answer !== '') {
            return $answer === 'Yes' ? 'Declared, file missing' : $answer;
        }

        return '';
    }

    private static function maskedAccount(mixed $number): string
    {
        $number = self::text($number);
        if ($number === '') {
            return '';
        }

        return str_repeat('X', 10).substr($number, -4);
    }

    private static function hours(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (! is_numeric($value)) {
            return self::text($value);
        }

        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }

    private static function dateLine(mixed $value): string
    {
        return RegistrationDisplay::formatProfileDateLine($value) ?? '';
    }

    private static function yesNo(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $text = is_string($value) ? strtolower(trim($value)) : (string) $value;
        if (in_array($text, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return 'Yes';
        }
        if (in_array($text, ['0', 'false', 'no', 'n', 'off'], true)) {
            return 'No';
        }

        return trim((string) $value);
    }

    private static function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    /**
     * @return array{bg: string, fg: string, bar: string}
     */
    private static function statusTone(string $status): array
    {
        return match (strtolower($status)) {
            'active' => ['bg' => '#d1fae5', 'fg' => '#065f46', 'bar' => '#059669'],
            'pending' => ['bg' => '#fef3c7', 'fg' => '#92400e', 'bar' => '#d97706'],
            'inactive' => ['bg' => '#e2e8f0', 'fg' => '#334155', 'bar' => '#64748b'],
            'declined', 'rejected' => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'bar' => '#dc2626'],
            default => ['bg' => '#e0e7ff', 'fg' => '#312e81', 'bar' => '#003d7a'],
        };
    }

    private static function profilePhoto(Employee $employee): ?string
    {
        return self::storedImage($employee->profile_photo_path, 480)['src'] ?? null;
    }

    /**
     * @param  list<array{label: string, path: mixed}>  $items
     * @return list<array{label: string, src: string, width: int, height: int}>
     */
    private static function collectImages(array $items): array
    {
        $images = [];
        foreach ($items as $item) {
            $image = self::storedImage($item['path'], 1600);
            if ($image === null) {
                continue;
            }
            $images[] = [
                'label' => $item['label'],
                'src' => $image['src'],
                'width' => $image['width'],
                'height' => $image['height'],
            ];
        }

        return $images;
    }

    /**
     * Front and back scans from ID, licence, and insurance rows.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, path: mixed}>
     */
    private static function documentImageItems(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = 'Document';
            }
            $front = $row['storage_path'] ?? null;
            $back = $row['back_storage_path'] ?? null;
            $hasFront = is_string($front) && $front !== '';
            $hasBack = is_string($back) && $back !== '';
            if ($hasFront) {
                $items[] = ['label' => $hasBack ? $title.' — front' : $title, 'path' => $front];
            }
            if ($hasBack) {
                $items[] = ['label' => $title.' — back', 'path' => $back];
            }
        }

        return $items;
    }

    /**
     * @return array{src: string, width: int, height: int}|null
     */
    private static function storedImage(mixed $path, int $maxEdge): ?array
    {
        $relative = self::text($path);
        if ($relative === '' || ! RegistrationDisplay::isLikelyImagePath($relative)) {
            return null;
        }
        if (! Storage::disk('employee_registration')->exists($relative)) {
            return null;
        }

        $absolute = Storage::disk('employee_registration')->path($relative);
        $src = self::embeddedImage($absolute, $maxEdge);
        $info = @getimagesize($absolute);
        if ($src === null || $info === false) {
            return null;
        }

        $width = max(1, (int) $info[0]);
        $height = max(1, (int) $info[1]);
        $scale = min(520 / $width, 640 / $height, 1);

        return [
            'src' => $src,
            'width' => max(1, (int) round($width * $scale)),
            'height' => max(1, (int) round($height * $scale)),
        ];
    }

    private static function logoImage(): ?string
    {
        $path = public_path('images/crulynk-logo.png');
        if (! is_file($path)) {
            return null;
        }

        return self::embeddedImage($path, 420);
    }

    private static function embeddedImage(string $absolutePath, int $maxEdge): ?string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }
        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        $type = (int) $info[2];
        $keepPng = in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)
            || (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP);
        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : IMAGETYPE_PNG], true)) {
            return null;
        }
        if ($type === IMAGETYPE_JPEG && $width <= $maxEdge && $height <= $maxEdge) {
            $binary = file_get_contents($absolutePath);

            return is_string($binary) && $binary !== '' ? 'data:image/jpeg;base64,'.base64_encode($binary) : null;
        }
        if ($type === IMAGETYPE_PNG && $width <= $maxEdge && $height <= $maxEdge) {
            $binary = file_get_contents($absolutePath);

            return is_string($binary) && $binary !== '' ? 'data:image/png;base64,'.base64_encode($binary) : null;
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($absolutePath) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($absolutePath) : false,
            IMAGETYPE_GIF => function_exists('imagecreatefromgif') ? @imagecreatefromgif($absolutePath) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };
        if ($source === false) {
            return null;
        }
        $scale = min($maxEdge / max(1, $width), $maxEdge / max(1, $height), 1);
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($targetW, $targetH);
        if ($resized === false) {
            imagedestroy($source);

            return null;
        }
        if ($keepPng) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $clear = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $targetW, $targetH, $clear);
            imagealphablending($resized, true);
        } else {
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefilledrectangle($resized, 0, 0, $targetW, $targetH, $white);
        }
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        imagedestroy($source);
        ob_start();
        if ($keepPng) {
            imagesavealpha($resized, true);
            imagepng($resized);
            $mime = 'image/png';
        } else {
            imagejpeg($resized, null, 82);
            $mime = 'image/jpeg';
        }
        $binary = ob_get_clean();
        imagedestroy($resized);

        return is_string($binary) && $binary !== '' ? 'data:'.$mime.';base64,'.base64_encode($binary) : null;
    }
}

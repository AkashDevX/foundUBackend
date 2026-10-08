<?php

namespace App\Models;

use App\Support\DisplayTimezone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'status',
    'incident_type',
    'site_name',
    'location',
    'occurred_at',
    'summary',
    'witnesses',
    'reported_to',
    'details',
    'attachment_path',
    'attachment_name',
    'attachment_mime',
    'admin_note',
    'reviewed_by',
    'reviewed_at',
])]
class IncidentReport extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    /** @var array<string, string> */
    public const TYPES = [
        'near_miss' => 'Near miss',
        'workplace_injury' => 'Workplace injury',
        'illness' => 'Illness / feeling physically unwell',
        'property_damage' => 'Property or equipment damage',
        'workplace_behaviour' => 'Workplace behaviour / conflict',
        'harassment' => 'Harassment or bullying concern',
        'safety_hazard' => 'Safety hazard',
        'other' => 'Other',
        'injury' => 'Injury',
        'hazard' => 'Hazard',
    ];

    /** @var array<string, string> */
    public const PERSON_TYPES = [
        'employee' => 'Employee',
        'contractor' => 'Contractor',
        'client' => 'Client',
        'customer' => 'Customer',
        'supplier' => 'Supplier / delivery driver',
        'tenant' => 'Tenant',
        'property_owner' => 'Property owner',
        'other' => 'Other',
    ];

    /** @var array<string, string> */
    public const EMPLOYMENT_TYPES = [
        'full_time' => 'Full-time',
        'part_time' => 'Part-time',
        'casual' => 'Casual',
        'volunteer' => 'Volunteer',
        'contractor' => 'Contractor',
        'na' => 'N/A',
    ];

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_NEW => 'New',
        self::STATUS_ACKNOWLEDGED => 'Acknowledged',
        self::STATUS_RESOLVED => 'Resolved',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function typeLabel(): string
    {
        $type = (string) $this->incident_type;
        $label = self::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type));
        $other = trim((string) $this->detail('incident_type_other'));
        if ($type === 'other' && $other !== '') {
            return $label.': '.$other;
        }

        return $label;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[(string) $this->status] ?? ucfirst((string) $this->status);
    }

    public function reporterName(): string
    {
        $employee = $this->employee;
        $name = trim((string) ($employee?->full_legal_name ?: ''));
        if ($name === '') {
            $name = trim((string) (($employee?->first_name ?? '').' '.($employee?->last_name ?? '')));
        }
        if ($name === '') {
            $name = trim((string) ($employee?->email ?: 'Employee'));
        }

        return $name;
    }

    public function hasAttachment(): bool
    {
        return is_string($this->attachment_path) && $this->attachment_path !== '';
    }

    /**
     * Grouped rows for the admin incident page. Blank answers are omitted.
     *
     * @return list<array{title: string, rows: list<array{label: string, value: string}>}>
     */
    /**
     * @return list<array{path: string, name?: string, mime?: string}>
     */
    public function storedFiles(): array
    {
        $files = is_array($this->details) ? ($this->details['files'] ?? null) : null;
        if (is_array($files) && $files !== []) {
            return array_values(array_filter($files, static function ($file): bool {
                return is_array($file) && is_string($file['path'] ?? null) && $file['path'] !== '';
            }));
        }

        if ($this->hasAttachment()) {
            return [[
                'path' => (string) $this->attachment_path,
                'name' => (string) ($this->attachment_name ?: 'incident-photo'),
                'mime' => (string) ($this->attachment_mime ?: ''),
            ]];
        }

        return [];
    }

    /**
     * @return array{width: int, height: int, strokes: list<list<array{x: float, y: float}>>}|null
     */
    public function signatureDrawing(): ?array
    {
        $details = is_array($this->details) ? $this->details : [];
        $strokes = $details['signature_strokes'] ?? null;
        if (! is_array($strokes) || $strokes === []) {
            return null;
        }

        return [
            'width' => max(1, (int) ($details['signature_width'] ?? 320)),
            'height' => max(1, (int) ($details['signature_height'] ?? 160)),
            'strokes' => $strokes,
        ];
    }

    /**
     * @return list<array{index: int, file: array{path: string, name?: string, mime?: string, group?: string}}>
     */
    public function filesFor(string $group): array
    {
        $matched = [];
        foreach ($this->storedFiles() as $index => $file) {
            $fileGroup = (string) ($file['group'] ?? 'incident');
            if ($fileGroup !== $group) {
                continue;
            }
            $matched[] = ['index' => $index, 'file' => $file];
        }

        return $matched;
    }

    public function presentationSections(): array
    {
        $stored = is_array($this->details) ? ($this->details['sections'] ?? null) : null;
        if (is_array($stored) && $stored !== []) {
            return array_values(array_filter($stored, static function ($section): bool {
                return is_array($section)
                    && is_string($section['title'] ?? null)
                    && is_array($section['rows'] ?? null)
                    && $section['rows'] !== [];
            }));
        }

        $sections = [
            [
                'title' => 'Incident',
                'rows' => $this->rows([
                    'Type' => $this->typeLabel(),
                    'Site' => $this->site_name,
                    'Location' => $this->location,
                    'When it happened' => DisplayTimezone::formatDateTime($this->occurred_at),
                    'What happened' => $this->summary,
                    'Witnesses' => $this->witnesses,
                    'Reported to' => $this->reported_to,
                    'Submitted' => DisplayTimezone::formatDateTime($this->created_at),
                ]),
            ],
            [
                'title' => 'Person involved',
                'rows' => $this->rows([
                    'Type of person' => $this->choiceLabel(self::PERSON_TYPES, 'person_type'),
                    'Name' => $this->detail('person_name'),
                    'Phone' => $this->detail('person_phone'),
                    'Address' => $this->detail('person_address'),
                    'Employment type' => $this->choiceLabel(self::EMPLOYMENT_TYPES, 'employment_type'),
                    'Gender' => $this->humanize($this->detail('person_gender')),
                    'Approximate age' => $this->detail('person_age'),
                    'Injured' => $this->humanize($this->detail('injured')),
                    'First aid provided' => $this->humanize($this->detail('first_aid')),
                    'Medical treatment required' => $this->humanize($this->detail('medical_treatment')),
                    'Under 18' => $this->humanize($this->detail('is_under_18')),
                ]),
            ],
        ];

        if ($this->detail('is_under_18') === 'yes') {
            $sections[] = [
                'title' => 'Accompanying adult',
                'rows' => $this->rows([
                    'Accompanied by an adult' => $this->humanize($this->detail('accompanied_by_adult')),
                    'Name' => $this->detail('accompanying_adult_name'),
                    'Relationship' => $this->detail('accompanying_adult_relationship'),
                    'Address' => $this->detail('accompanying_adult_address'),
                    'Contact number' => $this->detail('accompanying_adult_phone'),
                ]),
            ];
        }

        if (in_array((string) $this->incident_type, ['workplace_behaviour', 'harassment'], true)) {
            $sections[] = [
                'title' => 'Workplace behaviour',
                'rows' => $this->rows([
                    'Behaviour is ongoing' => $this->humanize($this->detail('behaviour_ongoing')),
                    'Reported previously' => $this->humanize($this->detail('previously_reported')),
                    'Previous report details' => $this->detail('previous_report_details'),
                    'Additional information' => $this->detail('behaviour_notes'),
                ]),
            ];
        }

        $sections[] = [
            'title' => 'Evidence',
            'rows' => $this->rows([
                'CCTV covering the area' => $this->humanize($this->detail('cctv')),
                'CCTV details' => $this->detail('cctv_details'),
                'Summary of footage' => $this->detail('cctv_summary'),
                'Footage reviewed by' => $this->detail('cctv_reviewer_name'),
                'Reviewer position' => $this->detail('cctv_reviewer_position'),
                'Date footage reviewed' => $this->detail('cctv_reviewed_on'),
                'Photo attached' => $this->hasAttachment() ? ($this->attachment_name ?: 'Yes') : null,
            ]),
        ];

        $sections[] = [
            'title' => 'Medical information (voluntary)',
            'rows' => $this->rows([
                'Pre-existing condition mentioned' => $this->humanize($this->detail('preexisting_condition')),
                'Condition details' => $this->detail('preexisting_details'),
                'Medication mentioned' => $this->humanize($this->detail('medication')),
                'Medication details' => $this->detail('medication_details'),
            ]),
        ];

        return array_values(array_filter(
            $sections,
            static fn (array $section): bool => $section['rows'] !== [],
        ));
    }

    public function detail(string $key): string
    {
        $details = is_array($this->details) ? $this->details : [];
        $value = $details[$key] ?? '';

        return trim((string) $value);
    }

    /**
     * @param  array<string, string|null>  $pairs
     * @return list<array{label: string, value: string}>
     */
    private function rows(array $pairs): array
    {
        $rows = [];
        foreach ($pairs as $label => $value) {
            $text = trim((string) $value);
            if ($text === '' || $text === '—') {
                continue;
            }
            $rows[] = ['label' => $label, 'value' => $text];
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function choiceLabel(array $map, string $key): string
    {
        $value = $this->detail($key);
        if ($value === '') {
            return '';
        }

        return $map[$value] ?? $this->humanize($value);
    }

    private function humanize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return match ($value) {
            'yes' => 'Yes',
            'no' => 'No',
            'na', 'n/a' => 'N/A',
            'no_obvious' => 'No obvious injury',
            'not_required' => 'Not required',
            'not_known' => 'Not known at the time of report',
            'unknown', 'unsure' => 'Unknown',
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other',
            default => ucfirst(str_replace('_', ' ', $value)),
        };
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'details' => 'array',
        ];
    }
};

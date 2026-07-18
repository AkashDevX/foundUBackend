<?php

namespace App\Models;

use App\Support\PayrollRateTypes;
use App\Support\RegistrationDisplay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'public_id',
    'registration_company_slug',
    'registration_company_app_key',
    'company_display_name',
    'employee_code',
    'first_name',
    'last_name',
    'full_legal_name',
    'email',
    'phone',
    'password',
    'date_of_birth',
    'sex',
    'marital_status',
    'address',
    'emergency_contact_name',
    'emergency_contact_phone',
    'emergency_contact_relationship',
    'visa_status',
    'unrestricted_work_rights',
    'visa_expiry',
    'hours_per_week',
    'weekly_availability_summary',
    'weekly_availability_json',
    'id_documents_summary',
    'id_documents_json',
    'police_check_expiry',
    'police_check_uploaded',
    'fit_to_work_expiry',
    'fit_to_work_uploaded',
    'licences_summary',
    'insurances_summary',
    'licences_json',
    'insurances_json',
    'bank_account_name',
    'bank_account_number',
    'bank_branch_code',
    'bank_name',
    'payroll_allowances_json',
    'sick_leave_balance_hours',
    'annual_leave_balance_hours',
    'sick_leave_balance_amount',
    'annual_leave_balance_amount',
    'mode_of_transport',
    'vehicle_registration',
    'vehicle_expiry',
    'vehicle_insurance_uploaded',
    'vehicle_insurance_path',
    'profile_photo_path',
    'police_check_path',
    'fit_to_work_path',
    'job_title',
    'job_title_id',
    'department',
    'department_id',
    'work_location_id',
    'shift_id',
    'assignment_effective_from',
    'assignment_notes',
    'employment_status',
    'employment_type',
    'award_level',
    'payroll_rates_json',
    'is_non_rotating_shift',
    'hired_at',
    'email_verified_at',
    'last_login_at',
    'profile_metadata',
])]
#[Hidden(['password', 'remember_token', 'bank_account_number'])]
class Employee extends Model
{
    use HasApiTokens;
    use SoftDeletes;

    /** Assigned org department (FK). Distinct from the legacy `department` registration text column. */
    public function assignedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function assignedJobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function assignedShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function timeClockEntries(): HasMany
    {
        return $this->hasMany(TimeClockEntry::class);
    }

    public function timesheetApprovals(): HasMany
    {
        return $this->hasMany(TimesheetApproval::class);
    }

    public function scheduleShifts(): HasMany
    {
        return $this->hasMany(EmployeeScheduleShift::class);
    }

    public function assignmentShifts(): HasMany
    {
        return $this->hasMany(EmployeeAssignmentShift::class)->orderBy('sort_order')->orderBy('id');
    }

    public function leaveRecords(): HasMany
    {
        return $this->hasMany(EmployeeLeaveRecord::class);
    }

    public function leaveEntitlements(): HasMany
    {
        return $this->hasMany(EmployeeLeaveEntitlement::class);
    }

    public function taskAssignments(): HasMany
    {
        return $this->hasMany(EmployeeTaskAssignment::class);
    }

    /**
     * Structured work assignment for mobile / JSON (shift, location, department, notes).
     *
     * @return array<string, mixed>|null
     */
    public function workAssignmentForApi(): ?array
    {
        $notes = $this->assignment_notes;
        $notesNonEmpty = is_string($notes) && trim($notes) !== '';

        $this->loadMissing(['assignedDepartment', 'workLocation', 'assignedShift', 'assignmentShifts.shiftTemplate']);

        $shiftPayloads = $this->assignmentShiftPayloads();
        $hasShifts = $shiftPayloads !== [];

        if (
            $this->department_id === null
            && $this->work_location_id === null
            && ! $hasShifts
            && ! $notesNonEmpty
            && $this->assignment_effective_from === null
        ) {
            return null;
        }

        $dept = $this->assignedDepartment;
        $loc = $this->workLocation;
        $primaryShift = $shiftPayloads[0] ?? null;

        return [
            'effective_from' => $this->assignment_effective_from?->toDateString(),
            'notes' => $notesNonEmpty ? trim((string) $notes) : null,
            'department' => $dept instanceof Department ? [
                'id' => $dept->id,
                'name' => $dept->name,
                'code' => $dept->code,
            ] : null,
            'work_location' => $loc instanceof WorkLocation ? [
                'id' => $loc->id,
                'name' => $loc->name,
                'address' => $loc->address,
                'notes' => $loc->notes,
                'latitude' => $loc->latitude,
                'longitude' => $loc->longitude,
            ] : null,
            'shifts' => $shiftPayloads,
            'shift' => $primaryShift,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function assignmentShiftPayloads(): array
    {
        $this->loadMissing(['assignmentShifts.shiftTemplate', 'assignedShift']);

        $fmtTime = static function ($value): ?string {
            if ($value === null) {
                return null;
            }
            if ($value instanceof CarbonInterface) {
                return $value->format('H:i');
            }

            return null;
        };

        $payloadFromShift = static function (Shift $shift, int $unpaidBreakMinutes) use ($fmtTime): array {
            return [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $fmtTime($shift->start_time),
                'end_time' => $fmtTime($shift->end_time),
                'breaks_summary' => $shift->breaks_summary,
                'unpaid_break_minutes' => $unpaidBreakMinutes,
                'notes' => $shift->notes,
                'shift_days' => is_array($shift->shift_days) ? $shift->shift_days : [],
                'days' => is_array($shift->shift_days) ? $shift->shift_days : [],
            ];
        };

        if ($this->assignmentShifts->isNotEmpty()) {
            $payloads = [];
            foreach ($this->assignmentShifts as $assignmentShift) {
                $shift = $assignmentShift->shiftTemplate;
                if (! $shift instanceof Shift) {
                    continue;
                }

                $payloads[] = $payloadFromShift($shift, (int) $assignmentShift->unpaid_break_minutes);
            }

            return $payloads;
        }

        if ($this->assignedShift instanceof Shift) {
            return [$payloadFromShift($this->assignedShift, 0)];
        }

        return [];
    }

    /**
     * GET /api/v1/me — fields from the tenant `employees` row for the mobile My Profile screen.
     * Omits password, tokens, and raw bank account number.
     *
     * @return array<string, mixed>
     */
    public function toMobileProfilePayload(?Company $tenantCompany = null): array
    {
        $assignment = $this->workAssignmentForApi();
        $assignedDepartment = is_array($assignment['department'] ?? null) ? $assignment['department'] : null;
        $assignedWorkLocation = is_array($assignment['work_location'] ?? null) ? $assignment['work_location'] : null;
        $assignedShift = is_array($assignment['shift'] ?? null) ? $assignment['shift'] : null;

        $this->loadMissing(['assignedJobTitle']);
        $jobTitleDisplay = $this->assignedJobTitle?->name ?? $this->job_title;

        return [
            'public_id' => $this->public_id,
            'company_slug' => $tenantCompany?->slug,
            'registration_company_slug' => $this->registration_company_slug,
            'company_name' => $this->company_display_name,
            'company_display_name' => $this->company_display_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_legal_name' => $this->full_legal_name,
            'date_of_birth' => RegistrationDisplay::toNullableIsoDate(
                RegistrationDisplay::employeeRawDateValue($this, 'date_of_birth', ['dateOfBirth', 'date_of_birth', 'dob', 'birthDate'])
            ),
            'sex' => $this->sex,
            'marital_status' => $this->marital_status,
            'address' => $this->address,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,
            'visa_status' => $this->visa_status,
            'unrestricted_work_rights' => $this->unrestricted_work_rights,
            'visa_expiry' => RegistrationDisplay::toNullableIsoDate(
                RegistrationDisplay::employeeRawDateValue($this, 'visa_expiry', ['visaExpiry', 'visa_expiry'])
            ),
            'hours_per_week' => $this->hours_per_week,
            'weekly_availability_summary' => $this->weekly_availability_summary,
            'weekly_availability_json' => $this->weekly_availability_json,
            'assigned_shift_days' => $assignedShift['shift_days'] ?? null,
            'id_documents_summary' => $this->id_documents_summary,
            'police_check_expiry' => RegistrationDisplay::toNullableIsoDate(
                RegistrationDisplay::employeeRawDateValue($this, 'police_check_expiry', ['policeCheckExpiry', 'police_check_expiry'])
            ),
            'police_check_uploaded' => $this->police_check_uploaded,
            'fit_to_work_expiry' => RegistrationDisplay::toNullableIsoDate(
                RegistrationDisplay::employeeRawDateValue($this, 'fit_to_work_expiry', ['fitToWorkExpiry', 'fit_to_work_expiry'])
            ),
            'fit_to_work_uploaded' => $this->fit_to_work_uploaded,
            'licences_summary' => $this->licences_summary,
            'insurances_summary' => $this->insurances_summary,
            'bank_account_name' => $this->bank_account_name,
            'bank_name' => $this->bank_name,
            'bank_branch_code' => $this->bank_branch_code,
            'mode_of_transport' => $this->mode_of_transport,
            'vehicle_registration' => $this->vehicle_registration,
            'vehicle_expiry' => RegistrationDisplay::toNullableIsoDate(
                RegistrationDisplay::employeeRawDateValue($this, 'vehicle_expiry', ['vehicleExpiry', 'vehicle_expiry'])
            ),
            'vehicle_insurance_uploaded' => $this->vehicle_insurance_uploaded,
            'employment_status' => $this->employment_status,
            'employee_code' => $this->employee_code,
            'job_title' => $jobTitleDisplay,
            'department' => $this->department,
            'role' => [
                'job_title' => $jobTitleDisplay,
                'department' => $assignedDepartment['name'] ?? $this->department,
                'employee_code' => $this->employee_code,
            ],
            'work_assignment' => $assignment,
            'assigned_shifts' => is_array($assignment) ? ($assignment['shifts'] ?? []) : [],
            // Flat aliases for mobile clients that map assignment fields at the profile root.
            'assigned_department' => $assignedDepartment['name'] ?? $this->department,
            'assigned_shift_name' => $assignedShift['name'] ?? null,
            'assigned_shift_start_time' => $assignedShift['start_time'] ?? null,
            'assigned_shift_end_time' => $assignedShift['end_time'] ?? null,
            'assigned_work_location_name' => $assignedWorkLocation['name'] ?? null,
            'assigned_work_location_address' => $assignedWorkLocation['address'] ?? null,
            'assigned_work_location_lat' => $assignedWorkLocation['latitude'] ?? null,
            'assigned_work_location_lng' => $assignedWorkLocation['longitude'] ?? null,
            'assigned_shift_date' => RegistrationDisplay::toNullableIsoDate($assignment['effective_from'] ?? null),
            'assigned_shift_status' => $this->employment_status,
            'assigned_department_code' => $assignedDepartment['code'] ?? null,
            'assigned_shift_breaks_summary' => $assignedShift['breaks_summary'] ?? null,
            'assigned_shift_notes' => $assignedShift['notes'] ?? null,
            'assigned_work_location_notes' => $assignedWorkLocation['notes'] ?? null,
            'assignment_notes' => is_array($assignment) ? ($assignment['notes'] ?? null) : null,
            'payroll' => [
                'employment_type' => $this->employment_type,
                'employment_type_label' => PayrollRateTypes::employmentTypeLabel($this->employment_type),
                'award_level' => $this->award_level,
                'award_level_label' => PayrollRateTypes::awardLevelLabel($this->award_level),
                'sick_leave_balance_hours' => (float) ($this->sick_leave_balance_hours ?? 0),
                'annual_leave_balance_hours' => (float) ($this->annual_leave_balance_hours ?? 0),
                'sick_leave_balance_amount' => (float) ($this->sick_leave_balance_amount ?? 0),
                'annual_leave_balance_amount' => (float) ($this->annual_leave_balance_amount ?? 0),
            ],
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $employee): void {
            if ($employee->public_id === null || $employee->public_id === '') {
                $employee->public_id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'bank_account_number' => 'encrypted',
            'hired_at' => 'date',
            'assignment_effective_from' => 'date',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'weekly_availability_json' => 'array',
            'id_documents_json' => 'array',
            'licences_json' => 'array',
            'insurances_json' => 'array',
            'payroll_allowances_json' => 'array',
            'payroll_rates_json' => 'array',
            'is_non_rotating_shift' => 'boolean',
            'sick_leave_balance_hours' => 'decimal:2',
            'annual_leave_balance_hours' => 'decimal:2',
            'sick_leave_balance_amount' => 'decimal:2',
            'annual_leave_balance_amount' => 'decimal:2',
            'profile_metadata' => 'array',
        ];
    }
}

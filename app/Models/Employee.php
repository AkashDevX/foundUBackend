<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
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
    'mode_of_transport',
    'vehicle_registration',
    'vehicle_expiry',
    'vehicle_insurance_uploaded',
    'vehicle_insurance_path',
    'profile_photo_path',
    'police_check_path',
    'fit_to_work_path',
    'job_title',
    'department',
    'department_id',
    'work_location_id',
    'shift_id',
    'assignment_effective_from',
    'assignment_notes',
    'employment_status',
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
    public function assignedDepartment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function workLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function assignedShift(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
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

        if (
            $this->department_id === null
            && $this->work_location_id === null
            && $this->shift_id === null
            && ! $notesNonEmpty
            && $this->assignment_effective_from === null
        ) {
            return null;
        }

        $this->loadMissing(['assignedDepartment', 'workLocation', 'assignedShift']);

        $shift = $this->assignedShift;
        $dept = $this->assignedDepartment;
        $loc = $this->workLocation;

        $fmtTime = static function ($value): ?string {
            if ($value === null) {
                return null;
            }
            if ($value instanceof \Carbon\CarbonInterface) {
                return $value->format('H:i');
            }

            return null;
        };

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
            ] : null,
            'shift' => $shift instanceof Shift ? [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $fmtTime($shift->start_time),
                'end_time' => $fmtTime($shift->end_time),
                'breaks_summary' => $shift->breaks_summary,
                'notes' => $shift->notes,
            ] : null,
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
            'profile_metadata' => 'array',
        ];
    }
}

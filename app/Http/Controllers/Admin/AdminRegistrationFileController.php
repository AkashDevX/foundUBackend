<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves employee_registration disk files to authenticated portal users for their org only.
 */
class AdminRegistrationFileController extends Controller
{
    private const DISK = 'employee_registration';

    /**
     * @var array<string, string>
     */
    private const SLOT_TO_ATTRIBUTE = [
        'profile-photo' => 'profile_photo_path',
        'police-check' => 'police_check_path',
        'fit-to-work' => 'fit_to_work_path',
        'vehicle-insurance' => 'vehicle_insurance_path',
    ];

    public function show(Request $request, string $companySlug, string $publicId, string $slot, ?string $itemKey = null): BinaryFileResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $sessionCompany = $portalUser->company()->firstOrFail();

        abort_unless($sessionCompany->slug === $companySlug, 403);

        /** @var Employee $employee */
        $employee = Employee::on($sessionCompany->tenant_connection)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $relativePath = $this->resolvePath($employee, $slot, $itemKey);
        if ($relativePath === null || $relativePath === '') {
            abort(404);
        }

        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        return response()->file($disk->path($relativePath), [
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($relativePath)).'"',
        ]);
    }

    private function resolvePath(Employee $employee, string $slot, ?string $itemKey): ?string
    {
        if (isset(self::SLOT_TO_ATTRIBUTE[$slot])) {
            $attr = self::SLOT_TO_ATTRIBUTE[$slot];
            $path = $employee->{$attr};

            return is_string($path) && $path !== '' ? $path : null;
        }

        $decodedKey = $itemKey !== null && $itemKey !== '' ? rawurldecode($itemKey) : null;

        return match ($slot) {
            'id-document' => $this->pathFromJsonRows($employee->id_documents_json, $decodedKey, 'documentKey'),
            'licence' => $this->pathFromJsonRows($employee->licences_json, $decodedKey, 'id'),
            'insurance' => $this->pathFromJsonRows($employee->insurances_json, $decodedKey, 'id'),
            default => null,
        };
    }

    /**
     * @param  array<int, mixed>|null  $rows
     */
    private function pathFromJsonRows(?array $rows, ?string $itemKey, string $matchField): ?string
    {
        if ($rows === null || $rows === [] || $itemKey === null || $itemKey === '') {
            return null;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $match = $row[$matchField] ?? null;
            if (! is_scalar($match)) {
                continue;
            }
            if ((string) $match !== (string) $itemKey) {
                continue;
            }
            $path = $row['storage_path'] ?? null;

            return is_string($path) && $path !== '' ? $path : null;
        }

        return null;
    }
}

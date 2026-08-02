<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use App\Support\RegistrationDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves employee_registration disk files to authenticated portal users for their org only.
 */
class AdminRegistrationFileController extends Controller
{
    private const DISK = 'employee_registration';

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

        $relativePath = RegistrationDisplay::registrationStoragePath($employee, $slot, $itemKey);
        if ($relativePath === null || $relativePath === '') {
            abort(404);
        }

        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        $headers = [
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($relativePath)).'"',
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ];

        $lastModified = $disk->lastModified($relativePath);
        if (is_int($lastModified) && $lastModified > 0) {
            $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $lastModified).' GMT';
            $headers['ETag'] = '"'.sha1($relativePath.'|'.$lastModified).'"';
        }

        return response()->file($disk->path($relativePath), $headers);
    }
}

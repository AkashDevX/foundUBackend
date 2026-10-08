<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\IncidentReport;
use App\Models\OrganizationPortalUser;
use App\Models\WorkLocation;
use App\Support\DisplayTimezone;
use App\Support\IncidentReportForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class EmployeeIncidentReportsController extends Controller
{
    public function options(): JsonResponse
    {
        $sites = $this->activeWorkLocationNames();

        $types = [];
        foreach (IncidentReport::TYPES as $value => $label) {
            $types[] = ['value' => $value, 'label' => $label];
        }

        return response()->json([
            'sites' => $sites,
            'types' => $types,
            'person_types' => $this->optionsFrom(IncidentReport::PERSON_TYPES),
            'employment_types' => $this->optionsFrom(IncidentReport::EMPLOYMENT_TYPES),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('incident_reports')) {
            return response()->json([
                'message' => 'Incident reporting is not available yet. Ask your administrator to update CruLynk.',
            ], 503);
        }

        /** @var Employee $employee */
        $employee = $request->user();

        $request->validate([
            'answers' => ['required', 'string'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'image', 'max:10240'],
            'property_photos' => ['nullable', 'array', 'max:5'],
            'property_photos.*' => ['file', 'image', 'max:10240'],
        ]);

        $decoded = json_decode((string) $request->input('answers'), true);
        if (! is_array($decoded)) {
            return response()->json(['message' => 'The incident form could not be read. Please try again.'], 422);
        }

        $prepared = IncidentReportForm::prepare($decoded, $this->activeWorkLocationNames());
        if (isset($prepared['error'])) {
            return response()->json(['message' => $prepared['error']], 422);
        }

        $files = array_merge(
            $this->storeUploads($request, 'photos', 'incident'),
            $this->storeUploads($request, 'property_photos', 'property'),
        );
        $incidentCount = (int) ($decoded['incident_photo_count'] ?? -1);
        $propertyCount = (int) ($decoded['property_photo_count'] ?? -1);
        if ($incidentCount >= 0 && $propertyCount >= 0 && count($files) === $incidentCount + $propertyCount) {
            foreach ($files as $index => $file) {
                $files[$index]['group'] = $index < $incidentCount ? 'incident' : 'property';
            }
        }
        $first = $files[0] ?? null;
        $prepared['details']['files'] = $files;

        /** @var IncidentReport $report */
        $report = IncidentReport::query()->create([
            'employee_id' => $employee->id,
            'status' => IncidentReport::STATUS_NEW,
            'attachment_path' => $first['path'] ?? null,
            'attachment_name' => $first['name'] ?? null,
            'attachment_mime' => $first['mime'] ?? null,
            ...$prepared['record'],
            'details' => $prepared['details'],
        ]);

        $report->setRelation('employee', $employee);
        $this->notifyAdmins($report);

        return response()->json([
            'message' => 'Incident report submitted. Your administrator has been alerted.',
            'incident' => [
                'id' => $report->id,
                'status' => $report->status,
                'type' => $report->typeLabel(),
            ],
        ], 201);
    }

    /**
     * @return list<string>
     */
    private function activeWorkLocationNames(): array
    {
        if (! Schema::hasTable('work_locations')) {
            return [];
        }

        return WorkLocation::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn ($name): string => trim((string) $name))
            ->filter(static fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $map
     * @return list<array{value: string, label: string}>
     */
    private function optionsFrom(array $map): array
    {
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return list<array{path: string, name: string, mime: string}>
     */
    private function storeUploads(Request $request, string $key, string $group): array
    {
        $uploaded = $request->file($key);
        if ($uploaded instanceof UploadedFile) {
            $uploaded = [$uploaded];
        }
        if (! is_array($uploaded)) {
            return [];
        }

        $company = $request->tenantCompany();
        $slug = trim((string) ($company?->slug ?: 'tenant'), '/');
        $stored = [];
        foreach ($uploaded as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $path = $file->store($slug.'/'.now()->format('Y/m'), 'incident_attachments');
            if (! is_string($path) || $path === '') {
                continue;
            }
            $stored[] = [
                'path' => $path,
                'name' => $file->getClientOriginalName() ?: 'incident-photo.jpg',
                'mime' => (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'image/jpeg'),
                'group' => $group,
            ];
        }

        return $stored;
    }

    private function notifyAdmins(IncidentReport $report): void
    {
        try {
            $company = request()->tenantCompany();
            if ($company === null) {
                return;
            }

            $emails = OrganizationPortalUser::query()
                ->where('company_id', $company->id)
                ->pluck('email')
                ->map(static fn ($email): string => trim((string) $email))
                ->filter(static fn (string $email): bool => $email !== '')
                ->unique()
                ->values();

            if ($emails->isEmpty()) {
                return;
            }

            $url = route('admin.incidents.show', $report->id);
            $body = implode("\n", [
                'A new incident report was submitted in CruLynk.',
                '',
                'Organization: '.$company->name,
                'Reported by: '.$report->reporterName(),
                'Type: '.$report->typeLabel(),
                'Site: '.$report->site_name,
                'Location: '.$report->location,
                'When: '.DisplayTimezone::formatDateTime($report->occurred_at),
                '',
                'What happened:',
                $report->summary,
                '',
                'Open the report: '.$url,
            ]);

            foreach ($emails as $email) {
                Mail::raw($body, function ($message) use ($email, $company): void {
                    $message->to($email)->subject('New incident report — '.$company->name);
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Incident admin email failed', [
                'incident_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

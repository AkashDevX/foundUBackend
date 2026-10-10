<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\IncidentReport;
use App\Models\OrganizationPortalUser;
use App\Support\DisplayTimezone;
use App\Support\IncidentReportAlerts;
use App\Support\IncidentReportPdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminIncidentReportsController extends Controller
{
    public function index(Request $request): View
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $ready = Schema::connection($ctx['connection'])->hasTable('incident_reports');
        $status = $this->listStatus($request);

        $reports = null;
        $counts = ['new' => 0, 'acknowledged' => 0, 'resolved' => 0];

        if ($ready) {
            $base = IncidentReport::on($ctx['connection']);
            $counts['new'] = (clone $base)->where('status', IncidentReport::STATUS_NEW)->count();
            $counts['acknowledged'] = (clone $base)->where('status', IncidentReport::STATUS_ACKNOWLEDGED)->count();
            $counts['resolved'] = (clone $base)->where('status', IncidentReport::STATUS_RESOLVED)->count();

            $query = IncidentReport::on($ctx['connection'])->with('employee')->orderByDesc('id');
            if ($status === 'open') {
                $query->whereIn('status', [IncidentReport::STATUS_NEW, IncidentReport::STATUS_ACKNOWLEDGED]);
            } elseif ($status !== 'all') {
                $query->where('status', $status);
            }

            $reports = $query->paginate(20)->withQueryString();
        }

        return view('admin.incidents.index', array_merge($ctx, [
            'ready' => $ready,
            'status' => $status,
            'counts' => $counts,
            'reports' => $reports,
        ]));
    }

    public function alerts(): JsonResponse
    {
        return response()->json(IncidentReportAlerts::forHeader());
    }

    public function export(Request $request): Response|RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        if (! Schema::connection($ctx['connection'])->hasTable('incident_reports')) {
            return redirect()
                ->route('admin.incidents.index')
                ->with('error', 'Incident reporting is not set up on this organization database yet.');
        }

        $status = $this->listStatus($request);
        $query = IncidentReport::on($ctx['connection'])->with('employee')->orderByDesc('id');
        if ($status === 'open') {
            $query->whereIn('status', [IncidentReport::STATUS_NEW, IncidentReport::STATUS_ACKNOWLEDGED]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $bytes = IncidentReportPdf::renderRegister($query->get(), (string) $ctx['company']->name, $status);

        return $this->pdfResponse($bytes, IncidentReportPdf::registerFilename($status));
    }

    public function show(Request $request, int $incident): View
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $report = IncidentReport::on($ctx['connection'])->with('employee')->findOrFail($incident);

        return view('admin.incidents.show', array_merge($ctx, [
            'report' => $report,
        ]));
    }

    public function pdf(Request $request, int $incident): Response
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $report = IncidentReport::on($ctx['connection'])->with('employee')->findOrFail($incident);

        return $this->pdfResponse(
            IncidentReportPdf::render($report, (string) $ctx['company']->name),
            IncidentReportPdf::filename($report),
        );
    }

    public function update(Request $request, int $incident): RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(IncidentReport::STATUSES))],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = IncidentReport::on($ctx['connection'])->findOrFail($incident);
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');

        $report->status = $data['status'];
        $report->admin_note = isset($data['admin_note']) && trim($data['admin_note']) !== ''
            ? trim($data['admin_note'])
            : null;
        if ($data['status'] === IncidentReport::STATUS_NEW) {
            $report->reviewed_by = null;
            $report->reviewed_at = null;
        } else {
            $report->reviewed_by = trim((string) ($portalUser->name ?: $portalUser->email));
            $report->reviewed_at = DisplayTimezone::now()->utc();
        }
        $report->save();

        $label = $report->statusLabel();

        return redirect()
            ->route('admin.incidents.show', $report->id)
            ->with('success', 'Incident marked as '.$label.'.');
    }

    public function attachment(Request $request, int $incident): BinaryFileResponse|RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $report = IncidentReport::on($ctx['connection'])->find($incident);
        $files = $report?->storedFiles() ?? [];
        $file = $files[$request->integer('file')] ?? null;
        if ($report === null || ! is_array($file)) {
            return redirect()->route('admin.incidents.index')->with('error', 'Attachment not found.');
        }

        $path = (string) ($file['path'] ?? '');
        if ($path === '' || ! Storage::disk('incident_attachments')->exists($path)) {
            return redirect()
                ->route('admin.incidents.show', $report->id)
                ->with('error', 'Attachment file is missing.');
        }

        return response()->file(
            Storage::disk('incident_attachments')->path($path),
            [
                'Content-Type' => ($file['mime'] ?? '') !== '' ? (string) $file['mime'] : 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes((string) ($file['name'] ?? 'incident-photo')).'"',
            ],
        );
    }

    private function pdfResponse(string $bytes, string $filename): Response
    {
        $safe = str_replace(['"', '\\', "\r", "\n"], '', $filename);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$safe.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function listStatus(Request $request): string
    {
        $status = (string) $request->query('status', 'open');
        if (! in_array($status, ['open', 'new', 'acknowledged', 'resolved', 'all'], true)) {
            return 'open';
        }

        return $status;
    }

    private function useTenant(string $connection): void
    {
        DB::setDefaultConnection($connection);
    }

    /**
     * @return array{company: Company, connection: string}
     */
    private function pageContext(Request $request): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();

        return [
            'company' => $company,
            'connection' => (string) $company->tenant_connection,
        ];
    }
}

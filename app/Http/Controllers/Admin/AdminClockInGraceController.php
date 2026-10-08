<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClockInException;
use App\Models\OrganizationPortalUser;
use App\Support\ClockInGrace;
use App\Support\ClockInGraceSettings;
use App\Support\DisplayTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminClockInGraceController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.dashboard', ['open_clock_in' => 1]);
    }

    /**
     * @return array{
     *     ready: bool,
     *     settings: array{grace_minutes: int, outside_policy: string, persisted: bool, ready: bool},
     *     pending: \Illuminate\Support\Collection<int, ClockInException>
     * }
     */
    public static function modalData(string $connection): array
    {
        $ready = Schema::connection($connection)->hasTable('time_clock_settings')
            && Schema::connection($connection)->hasTable('clock_in_exceptions');
        $settings = ClockInGraceSettings::current($connection);
        $pending = collect();

        if ($ready) {
            $pending = ClockInException::on($connection)
                ->with('employee')
                ->where('status', ClockInException::STATUS_PENDING)
                ->orderByDesc('attempted_at')
                ->limit(100)
                ->get();
        }

        return [
            'ready' => $ready,
            'settings' => $settings,
            'pending' => $pending,
        ];
    }

    public function update(Request $request): RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        if (! $this->tablesReady($ctx['connection'])) {
            return $this->dashboardRedirect()
                ->with('error', 'Clock-in timing is not set up for this organization yet.');
        }

        $data = $request->validate([
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'outside_policy' => ['required', 'string', Rule::in([
                ClockInGrace::POLICY_PREVENT,
                ClockInGrace::POLICY_EXCEPTION,
            ])],
        ]);

        ClockInGraceSettings::save(
            $ctx['connection'],
            (int) $data['grace_minutes'],
            (string) $data['outside_policy'],
        );

        $example = ClockInGrace::exampleLabels((int) $data['grace_minutes']);

        return $this->dashboardRedirect()
            ->with('success', sprintf(
                'Saved. For a %s shift, staff can clock in from %s to %s.',
                $example['start'],
                $example['earliest'],
                $example['latest'],
            ));
    }

    public function clear(Request $request, int $exception): RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(['allow', 'dismiss'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = ClockInException::on($ctx['connection'])->where('status', ClockInException::STATUS_PENDING)->find($exception);
        if (! $row instanceof ClockInException) {
            return $this->dashboardRedirect()
                ->with('error', 'That request is no longer waiting for approval.');
        }

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $note = isset($data['admin_note']) ? trim((string) $data['admin_note']) : '';

        $row->admin_note = $note !== '' ? $note : $row->admin_note;
        $row->cleared_by = trim((string) ($portalUser->name ?: $portalUser->email));
        $row->cleared_at = DisplayTimezone::now()->utc();
        $row->status = $data['action'] === 'allow'
            ? ClockInException::STATUS_CLEARED
            : ClockInException::STATUS_VOID;
        $row->save();

        $message = $row->status === ClockInException::STATUS_CLEARED
            ? 'Clock-in allowed. They can clock in now.'
            : 'Request dismissed. They cannot clock in until they try again.';

        return $this->dashboardRedirect()->with('success', $message);
    }

    private function dashboardRedirect(): RedirectResponse
    {
        return redirect()->route('admin.dashboard', ['open_clock_in' => 1]);
    }

    private function tablesReady(string $connection): bool
    {
        return Schema::connection($connection)->hasTable('time_clock_settings')
            && Schema::connection($connection)->hasTable('clock_in_exceptions');
    }

    private function useTenant(string $connection): void
    {
        DB::setDefaultConnection($connection);
    }

    /**
     * @return array{company: \App\Models\Company, connection: string}
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

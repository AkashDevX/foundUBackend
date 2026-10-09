<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizationPortalUser;
use App\Support\BreakWindow;
use App\Support\BreakWindowSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminBreakWindowController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        if (! $this->tablesReady($ctx['connection'])) {
            return $this->redirectBack()
                ->with('error', 'Break rules are not set up for this organization yet. Apply the latest update, then try again.');
        }

        $data = $request->validate([
            'break_rule_enabled' => ['required', 'boolean'],
            'start_hours' => ['required', 'numeric', 'min:1', 'max:12'],
            'end_hours' => ['required', 'numeric', 'min:1', 'max:16'],
            'required_after_hours' => ['required', 'numeric', 'min:0', 'max:16'],
            'reminder_lead_minutes' => ['required', 'integer', 'min:0', 'max:120'],
        ]);

        $startMinutes = BreakWindow::hoursToMinutes((float) $data['start_hours']);
        $endMinutes = BreakWindow::hoursToMinutes((float) $data['end_hours']);
        if ($endMinutes <= $startMinutes) {
            return $this->redirectBack()
                ->withErrors(['end_hours' => 'The break window has to close after it opens.'])
                ->withInput();
        }

        BreakWindowSettings::save(
            $ctx['connection'],
            (bool) $data['break_rule_enabled'],
            $startMinutes,
            $endMinutes,
            BreakWindow::hoursToMinutes((float) $data['required_after_hours']),
            (int) $data['reminder_lead_minutes'],
        );

        $sentence = BreakWindow::exampleSentence($startMinutes, $endMinutes);

        return $this->redirectBack()
            ->with('success', $data['break_rule_enabled']
                ? 'Break rules saved. On a 6:00 AM shift, employees see: '.$sentence
                : 'Break rules saved. Employees can take a break at any time during the shift.');
    }

    private function redirectBack(): RedirectResponse
    {
        return redirect()->route('admin.employees.time-clock', ['break_rules' => 1]);
    }

    private function tablesReady(string $connection): bool
    {
        return Schema::connection($connection)->hasTable('time_clock_settings')
            && Schema::connection($connection)->hasColumn('time_clock_settings', 'break_rule_enabled');
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

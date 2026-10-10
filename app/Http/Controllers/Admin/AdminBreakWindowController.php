<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
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
                ->with('error', 'Meal break settings are not ready yet. Refresh this page and try again.');
        }

        $data = $request->validate([
            'break_rule_enabled' => ['required', 'boolean'],
            'start_hours' => ['required', 'numeric', 'min:1', 'max:12'],
            'end_hours' => ['required', 'numeric', 'min:1', 'max:16'],
            'required_after_hours' => ['required', 'numeric', 'min:0', 'max:16'],
            'reminder_lead_minutes' => ['required', 'integer', 'min:0', 'max:120'],
        ], [
            'start_hours.required' => 'Enter the from hour.',
            'start_hours.numeric' => 'Enter the from hour as a number.',
            'start_hours.min' => 'From hour has to be at least 1.',
            'start_hours.max' => 'From hour cannot be more than 12.',
            'end_hours.required' => 'Enter the until hour.',
            'end_hours.numeric' => 'Enter the until hour as a number.',
            'end_hours.min' => 'Until hour has to be at least 1.',
            'end_hours.max' => 'Until hour cannot be more than 16.',
            'required_after_hours.required' => 'Enter how long the shift must be.',
            'required_after_hours.numeric' => 'Enter the shift length as a number.',
            'required_after_hours.min' => 'Shift length cannot be less than 0.',
            'required_after_hours.max' => 'Shift length cannot be more than 16.',
            'reminder_lead_minutes.required' => 'Enter how many minutes before to remind employees.',
            'reminder_lead_minutes.integer' => 'Enter the reminder as whole minutes.',
            'reminder_lead_minutes.min' => 'The reminder cannot be less than 0 minutes.',
            'reminder_lead_minutes.max' => 'The reminder cannot be more than 120 minutes.',
        ]);

        $startMinutes = BreakWindow::hoursToMinutes((float) $data['start_hours']);
        $endMinutes = BreakWindow::hoursToMinutes((float) $data['end_hours']);
        if ($endMinutes <= $startMinutes) {
            return $this->redirectBack()
                ->withErrors(['end_hours' => 'Until hour has to be later than from hour.'])
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

        return $this->redirectBack()
            ->with('success', $data['break_rule_enabled']
                ? 'Saved.'
                : 'Saved. Employees can take a break at any time.');
    }

    private function redirectBack(): RedirectResponse
    {
        return redirect()->route('admin.employees.weekly-schedule', ['break_rules' => 1]);
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

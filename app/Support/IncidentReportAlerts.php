<?php

namespace App\Support;

use App\Models\IncidentReport;
use App\Models\OrganizationPortalUser;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Open incident reports for the admin header siren.
 */
final class IncidentReportAlerts
{
    /**
     * @return array{
     *     new_count: int,
     *     ready: bool,
     *     index_url: string,
     *     latest_id: int,
     *     items: list<array{id: int, title: string, subtitle: string, detail: string, status: string, status_label: string, url: string, when: string}>
     * }
     */
    public static function forHeader(): array
    {
        $indexUrl = route('admin.incidents.index');
        $empty = [
            'new_count' => 0,
            'ready' => false,
            'index_url' => $indexUrl,
            'latest_id' => 0,
            'items' => [],
        ];

        try {
            /** @var OrganizationPortalUser|null $user */
            $user = auth('portal')->user();
            $company = $user?->company;
            $connection = $company?->tenant_connection;
            if (! is_string($connection) || $connection === '' || ($company?->isPlatformController() ?? false)) {
                return $empty;
            }

            if (! Schema::connection($connection)->hasTable('incident_reports')) {
                return $empty;
            }

            $newCount = (int) IncidentReport::on($connection)
                ->where('status', IncidentReport::STATUS_NEW)
                ->count();
            $latestId = (int) IncidentReport::on($connection)
                ->where('status', IncidentReport::STATUS_NEW)
                ->max('id');

            $reports = IncidentReport::on($connection)
                ->with('employee')
                ->where('status', IncidentReport::STATUS_NEW)
                ->orderByDesc('id')
                ->limit(8)
                ->get();

            $items = [];
            foreach ($reports as $report) {
                $site = self::readable($report->site_name);
                $items[] = [
                    'id' => (int) $report->id,
                    'title' => $report->typeLabel(),
                    'subtitle' => trim($report->reporterName().($site !== '' ? ' · '.$site : '')),
                    'detail' => self::readable($report->summary),
                    'status' => (string) $report->status,
                    'status_label' => $report->statusLabel(),
                    'url' => route('admin.incidents.show', $report->id),
                    'when' => DisplayTimezone::formatDateTime($report->created_at),
                ];
            }

            return [
                'new_count' => $newCount,
                'ready' => true,
                'index_url' => $indexUrl,
                'latest_id' => $latestId,
                'items' => $items,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    private static function readable(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '' || strcasecmp($text, 'Not specified') === 0) {
            return '';
        }

        return $text;
    }
}

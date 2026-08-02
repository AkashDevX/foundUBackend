<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Aggregates limited, non-sensitive tenant organization summaries for the platform UI.
 */
class PlatformOrganizationSummary
{
    /**
     * @return Collection<int, array{
     *     company: Company,
     *     operational: bool,
     *     stats_total: int|null,
     *     stats_pending: int|null,
     *     stats_active: int|null,
     *     stats_declined: int|null
     * }>
     */
    public static function forAllTenants(): Collection
    {
        return Company::query()
            ->tenantOrganizations()
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company): array => self::forCompany($company));
    }

    /**
     * @return array{
     *     company: Company,
     *     operational: bool,
     *     stats_total: int|null,
     *     stats_pending: int|null,
     *     stats_active: int|null,
     *     stats_declined: int|null
     * }
     */
    public static function forCompany(Company $company): array
    {
        $operational = false;
        $statsTotal = null;
        $statsPending = null;
        $statsActive = null;
        $statsDeclined = null;

        if ($company->hasTenantDatabase()) {
            try {
                $conn = $company->tenant_connection;
                $statsTotal = Employee::on($conn)->count();
                $statsPending = Employee::on($conn)->where('employment_status', 'pending')->count();
                $statsActive = Employee::on($conn)->where('employment_status', 'active')->count();
                $statsDeclined = Employee::on($conn)->whereIn('employment_status', ['declined', 'rejected'])->count();
                $operational = true;
            } catch (\Throwable) {
                // Do not expose internal error details in the platform UI.
            }
        }

        return [
            'company' => $company,
            'operational' => $operational,
            'stats_total' => $statsTotal,
            'stats_pending' => $statsPending,
            'stats_active' => $statsActive,
            'stats_declined' => $statsDeclined,
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use App\Services\FcmPushService;
use App\Support\DisplayTimezone;
use App\Support\DocumentRenewal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Daily push for documents that expire within 30 days, or are already expired,
 * until the employee uploads a renewed copy and sets a later expiry.
 */
class NotifyDocumentRenewals extends Command
{
    protected $signature = 'documents:renewal-reminders {--dry-run : Report who would be notified without sending}';

    protected $description = 'Notify employees every day until they upload renewed documents and set a new expiry.';

    public function handle(FcmPushService $fcm): int
    {
        $originalConnection = DB::getDefaultConnection();
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $today = DisplayTimezone::now()->toDateString();

        $companies = Company::query()
            ->tenantOrganizations()
            ->where('is_active', true)
            ->whereNotNull('tenant_connection')
            ->get();

        foreach ($companies as $company) {
            if (! $company->hasTenantDatabase()) {
                continue;
            }

            try {
                DB::setDefaultConnection($company->tenant_connection);
                $sent += $this->processTenant($company, $fcm, $dryRun, $today);
            } catch (Throwable $e) {
                $this->error("[{$company->slug}] {$e->getMessage()}");
            } finally {
                DB::setDefaultConnection($originalConnection);
            }
        }

        $verb = $dryRun ? 'would notify' : 'notified';
        $this->info("Document renewal reminders complete. {$verb} {$sent} employee(s).");

        return self::SUCCESS;
    }

    private function processTenant(Company $company, FcmPushService $fcm, bool $dryRun, string $today): int
    {
        $count = 0;

        Employee::query()
            ->where('employment_status', 'active')
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($company, $fcm, $dryRun, $today, &$count): void {
                foreach ($employees as $employee) {
                    $items = DocumentRenewal::dueItems($employee);
                    if ($items === []) {
                        continue;
                    }

                    $copy = DocumentRenewal::reminderCopy($items);
                    if ($copy['title'] === '' || $copy['body'] === '') {
                        continue;
                    }

                    $count++;
                    if ($dryRun || ! $fcm->isEnabled()) {
                        continue;
                    }

                    $cacheKey = 'document-renewal:'.$company->slug.':'.$employee->id.':'.$today;
                    if (! Cache::add($cacheKey, 1, now()->addHours(30))) {
                        continue;
                    }

                    $fcm->sendToEmployees([(int) $employee->id], [
                        'title' => $copy['title'],
                        'body' => $copy['body'],
                        'data' => [
                            'kind' => 'document_renewal',
                            'screen' => 'MyProfile',
                        ],
                    ]);
                }
            });

        if (! $dryRun && ! $fcm->isEnabled() && $count > 0) {
            $this->line("[{$company->slug}] FCM is off. {$count} employee(s) still get the daily reminder from the mobile app.");
        }

        return $count;
    }
}

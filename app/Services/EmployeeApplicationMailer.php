<?php

namespace App\Services;

use App\Mail\EmployeeApplicationStatusMail;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends registration lifecycle emails. Delivery failures are logged and never
 * block the application from being saved or reviewed.
 */
class EmployeeApplicationMailer
{
    public function sendReceived(Employee $employee, Company $company): void
    {
        $this->send($employee, $company, EmployeeApplicationStatusMail::RECEIVED);
    }

    public function sendApproved(Employee $employee, Company $company): void
    {
        $this->send($employee, $company, EmployeeApplicationStatusMail::APPROVED);
    }

    public function sendDeclined(Employee $employee, Company $company): void
    {
        $this->send($employee, $company, EmployeeApplicationStatusMail::DECLINED);
    }

    private function send(Employee $employee, Company $company, string $status): void
    {
        $email = trim((string) $employee->email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Log::warning('Application status email skipped: employee has no valid email.', [
                'status' => $status,
                'company_slug' => $company->slug,
                'employee_public_id' => $employee->public_id,
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new EmployeeApplicationStatusMail($employee, $company, $status));
        } catch (Throwable $e) {
            Log::warning('Application status email failed.', [
                'status' => $status,
                'company_slug' => $company->slug,
                'employee_public_id' => $employee->public_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

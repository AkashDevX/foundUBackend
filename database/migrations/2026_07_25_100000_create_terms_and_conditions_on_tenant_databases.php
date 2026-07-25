<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization Terms & Conditions shown in the mobile create-account modal.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaultContent = implode("\n\n", [
            "1. Introduction\n\nWelcome to CruLynk. By creating an account and using this mobile application, you agree to these Terms and Conditions. Please read them carefully before completing your registration.",
            "2. Eligibility\n\nYou must be legally permitted to work in Australia and provide accurate, complete information during registration. False or misleading details may result in rejection or termination of your application.",
            "3. Your account\n\nYou are responsible for keeping your login credentials secure and for all activity under your account. Notify your employer or CruLynk support immediately if you suspect unauthorised access.",
            "4. Employment and payroll data\n\nInformation you submit—including bank details, identification documents, qualifications, and availability—may be used by your organisation for rostering, compliance, and payroll purposes in accordance with applicable law.",
            "5. Privacy\n\nWe collect and process personal information to operate the service. Documents you upload are stored securely and shared only with authorised personnel at your workplace and service providers who assist in delivering the platform.",
            "6. Acceptable use\n\nYou agree not to misuse the app, attempt to access data belonging to others, or upload content that is unlawful, offensive, or infringes third-party rights. We may suspend access where misuse is detected.",
            "7. Changes\n\nThese terms may be updated from time to time. Continued use of the app after changes are published constitutes acceptance of the revised terms. Material changes will be communicated where reasonably practicable.",
        ]);

        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('terms_and_conditions')) {
                continue;
            }

            Schema::connection($connection)->create('terms_and_conditions', function (Blueprint $table) {
                $table->id();
                $table->longText('content');
                $table->date('last_updated_on')->nullable();
                $table->timestamps();
            });

            DB::connection($connection)->table('terms_and_conditions')->insert([
                'content' => $defaultContent,
                'last_updated_on' => '2026-06-02',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('terms_and_conditions');
        }
    }
};

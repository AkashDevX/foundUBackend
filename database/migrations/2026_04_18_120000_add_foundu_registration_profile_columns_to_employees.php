<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Columns aligned with foundU Create Account steps (1–4) and UserProfileSnapshot.
 *
 * Structured lists (weekly grid, ID docs, licences, insurances) use JSON where the
 * UI captures more than summary strings; upload URIs should be replaced by server
 * paths after you add file storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('employees')) {
                continue;
            }
            if (Schema::connection($connection)->hasColumn('employees', 'full_legal_name')) {
                continue;
            }

            Schema::connection($connection)->table('employees', function (Blueprint $table) {
                // Step 1 — company picker + identity + address + emergency contact
                $table->string('company_app_id', 64)->nullable()->after('public_id')->index();
                $table->string('company_display_name', 200)->nullable()->after('company_app_id');
                $table->string('full_legal_name', 200)->nullable()->after('last_name');

                $table->string('date_of_birth', 32)->nullable()->after('phone');
                $table->string('sex', 16)->nullable();
                $table->string('marital_status', 64)->nullable();
                $table->text('address')->nullable();

                $table->string('emergency_contact_name', 160)->nullable();
                $table->string('emergency_contact_phone', 48)->nullable();
                $table->string('emergency_contact_relationship', 120)->nullable();

                // Step 2 — work rights, availability, ID docs (structured + summaries)
                $table->string('visa_status', 120)->nullable();
                $table->string('unrestricted_work_rights', 8)->nullable();
                $table->string('visa_expiry', 32)->nullable();

                $table->string('hours_per_week', 16)->nullable();
                $table->text('weekly_availability_summary')->nullable();
                $table->json('weekly_availability_json')->nullable();

                $table->text('id_documents_summary')->nullable();
                $table->json('id_documents_json')->nullable();

                // Step 3 — qualifications
                $table->string('police_check_expiry', 32)->nullable();
                $table->string('police_check_uploaded', 8)->nullable();
                $table->string('fit_to_work_expiry', 32)->nullable();
                $table->string('fit_to_work_uploaded', 8)->nullable();

                $table->text('licences_summary')->nullable();
                $table->text('insurances_summary')->nullable();
                $table->json('licences_json')->nullable();
                $table->json('insurances_json')->nullable();

                // Step 4 — payroll + transport (+ sensitive banking)
                $table->string('bank_account_name', 160)->nullable();
                $table->text('bank_account_number')->nullable();
                $table->string('bank_branch_code', 32)->nullable();
                $table->string('bank_name', 160)->nullable();

                $table->string('mode_of_transport', 64)->nullable();
                $table->string('vehicle_registration', 64)->nullable();
                $table->string('vehicle_expiry', 32)->nullable();
                $table->string('vehicle_insurance_uploaded', 8)->nullable();

                // Future: avatar from Step 1 “Upload Photo”
                $table->string('profile_photo_path', 512)->nullable();
            });

            // Widen phone for international formats; avoids requiring doctrine/dbal for ->change().
            try {
                DB::connection($connection)->statement(
                    'ALTER TABLE employees MODIFY phone VARCHAR(48) NULL'
                );
            } catch (\Throwable) {
                /* column width may already match */
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->table('employees', function (Blueprint $table) {
                $table->dropColumn([
                    'company_app_id',
                    'company_display_name',
                    'full_legal_name',
                    'date_of_birth',
                    'sex',
                    'marital_status',
                    'address',
                    'emergency_contact_name',
                    'emergency_contact_phone',
                    'emergency_contact_relationship',
                    'visa_status',
                    'unrestricted_work_rights',
                    'visa_expiry',
                    'hours_per_week',
                    'weekly_availability_summary',
                    'weekly_availability_json',
                    'id_documents_summary',
                    'id_documents_json',
                    'police_check_expiry',
                    'police_check_uploaded',
                    'fit_to_work_expiry',
                    'fit_to_work_uploaded',
                    'licences_summary',
                    'insurances_summary',
                    'licences_json',
                    'insurances_json',
                    'bank_account_name',
                    'bank_account_number',
                    'bank_branch_code',
                    'bank_name',
                    'mode_of_transport',
                    'vehicle_registration',
                    'vehicle_expiry',
                    'vehicle_insurance_uploaded',
                    'profile_photo_path',
                ]);
            });

            DB::connection($connection)->statement(
                'ALTER TABLE employees MODIFY phone VARCHAR(32) NULL'
            );
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New organisation access requests from the mobile app (master DB only).
 * Visible in the CruLynk platform portal — not in tenant organization admin UI.
 */
return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('organization_requests')) {
            return;
        }

        Schema::connection($this->connection)->create('organization_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('industry', 120);
            $table->string('industry_other', 255)->nullable();
            $table->string('employee_band', 64);
            $table->string('employee_band_other', 64)->nullable();
            $table->string('postcode', 32);
            $table->string('contact_full_name', 200);
            $table->string('contact_email');
            $table->string('contact_telephone', 48);
            $table->string('status', 32)->default('pending');
            $table->string('source', 32)->default('mobile_app');
            $table->timestamps();

            $table->index(['platform_company_id', 'status', 'created_at'], 'org_req_platform_status_idx');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('organization_requests');
    }
};

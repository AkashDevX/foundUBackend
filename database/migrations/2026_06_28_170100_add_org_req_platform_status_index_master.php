<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('organization_requests')) {
            return;
        }

        Schema::connection($this->connection)->table('organization_requests', function (Blueprint $table): void {
            $table->index(['platform_company_id', 'status', 'created_at'], 'org_req_platform_status_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('organization_requests')) {
            return;
        }

        Schema::connection($this->connection)->table('organization_requests', function (Blueprint $table): void {
            $table->dropIndex('org_req_platform_status_idx');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('companies')) {
            return;
        }

        Schema::connection($this->connection)->table('companies', function (Blueprint $table) {
            if (! Schema::connection($this->connection)->hasColumn('companies', 'is_platform_controller')) {
                $table->boolean('is_platform_controller')->default(false)->after('is_active');
            }
        });

        Schema::connection($this->connection)->table('companies', function (Blueprint $table) {
            $table->string('database_name')->nullable()->change();
            $table->string('tenant_connection')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('companies')) {
            return;
        }

        Schema::connection($this->connection)->table('companies', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('companies', 'is_platform_controller')) {
                $table->dropColumn('is_platform_controller');
            }
        });
    }
};

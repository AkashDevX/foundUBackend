<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digital signature captured in the app when a certificate is earned.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                Schema::connection($connection)->hasTable('training_certificates')
                && ! Schema::connection($connection)->hasColumn('training_certificates', 'signature_strokes')
            ) {
                Schema::connection($connection)->table('training_certificates', function (Blueprint $table): void {
                    $table->unsignedSmallInteger('signature_width')->nullable()->after('expires_on');
                    $table->unsignedSmallInteger('signature_height')->nullable()->after('signature_width');
                    $table->json('signature_strokes')->nullable()->after('signature_height');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                Schema::connection($connection)->hasTable('training_certificates')
                && Schema::connection($connection)->hasColumn('training_certificates', 'signature_strokes')
            ) {
                Schema::connection($connection)->table('training_certificates', function (Blueprint $table): void {
                    $table->dropColumn(['signature_width', 'signature_height', 'signature_strokes']);
                });
            }
        }
    }
};

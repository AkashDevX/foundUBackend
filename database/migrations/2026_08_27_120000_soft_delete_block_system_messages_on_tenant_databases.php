<?php

use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove temporary block/unblock system notices that were posted into threads.
 */
return new class extends Migration
{
    private const BODIES = [
        'Messaging is paused because of a block.',
        'Messaging is available again.',
        'You blocked this conversation. Messaging is paused until someone unblocks.',
    ];

    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('messages')) {
                continue;
            }

            DB::connection($connection)
                ->table('messages')
                ->where('message_type', Message::TYPE_SYSTEM)
                ->whereIn('body', self::BODIES)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }
    }

    public function down(): void
    {
        // Soft-deleted notices are not restored.
    }
};

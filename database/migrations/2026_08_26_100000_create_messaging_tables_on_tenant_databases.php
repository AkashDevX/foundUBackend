<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Org messaging: conversations, messages, blocks, reports, messaging policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaultPolicy = \App\Support\MessagingPolicyText::defaultContent();

        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasColumn('employees', 'messaging_disabled_at')) {
                Schema::connection($connection)->table('employees', function (Blueprint $table) {
                    $table->timestamp('messaging_disabled_at')->nullable()->after('last_login_at');
                });
            }

            if (! Schema::connection($connection)->hasTable('conversations')) {
                Schema::connection($connection)->create('conversations', function (Blueprint $table) {
                    $table->id();
                    $table->string('type', 16); // direct|group
                    $table->string('title')->nullable();
                    $table->string('created_by_type', 32);
                    $table->unsignedBigInteger('created_by_id');
                    $table->timestamp('last_message_at')->nullable()->index();
                    $table->timestamps();
                });
            }

            if (! Schema::connection($connection)->hasTable('conversation_participants')) {
                Schema::connection($connection)->create('conversation_participants', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                    $table->string('participant_type', 32);
                    $table->unsignedBigInteger('participant_id');
                    $table->timestamp('last_read_at')->nullable();
                    $table->timestamp('left_at')->nullable();
                    $table->timestamps();

                    $table->unique(
                        ['conversation_id', 'participant_type', 'participant_id'],
                        'conv_participant_unique'
                    );
                    $table->index(['participant_type', 'participant_id'], 'conv_participant_lookup');
                });
            }

            if (! Schema::connection($connection)->hasTable('messages')) {
                Schema::connection($connection)->create('messages', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                    $table->string('sender_type', 32);
                    $table->unsignedBigInteger('sender_id');
                    $table->text('body')->nullable();
                    $table->string('message_type', 16)->default('text'); // text|image|file|system
                    $table->string('attachment_path')->nullable();
                    $table->string('attachment_mime', 128)->nullable();
                    $table->string('attachment_name')->nullable();
                    $table->unsignedInteger('attachment_size')->nullable();
                    $table->softDeletes();
                    $table->timestamps();

                    $table->index(['conversation_id', 'id']);
                });
            }

            if (! Schema::connection($connection)->hasTable('employee_blocks')) {
                Schema::connection($connection)->create('employee_blocks', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('blocker_employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->foreignId('blocked_employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->timestamps();

                    $table->unique(['blocker_employee_id', 'blocked_employee_id'], 'employee_blocks_unique');
                });
            }

            if (! Schema::connection($connection)->hasTable('message_reports')) {
                Schema::connection($connection)->create('message_reports', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                    $table->string('reporter_type', 32);
                    $table->unsignedBigInteger('reporter_id');
                    $table->text('reason');
                    $table->string('status', 16)->default('open'); // open|resolved|dismissed
                    $table->text('reviewer_notes')->nullable();
                    $table->timestamp('reviewed_at')->nullable();
                    $table->timestamps();

                    $table->index(['status', 'created_at']);
                });
            }

            if (! Schema::connection($connection)->hasTable('messaging_policies')) {
                Schema::connection($connection)->create('messaging_policies', function (Blueprint $table) {
                    $table->id();
                    $table->longText('content');
                    $table->unsignedInteger('version')->default(1);
                    $table->date('last_updated_on')->nullable();
                    $table->timestamps();
                });

                DB::connection($connection)->table('messaging_policies')->insert([
                    'content' => $defaultPolicy,
                    'version' => 1,
                    'last_updated_on' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (! Schema::connection($connection)->hasTable('messaging_policy_acceptances')) {
                Schema::connection($connection)->create('messaging_policy_acceptances', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->unsignedInteger('policy_version');
                    $table->timestamp('accepted_at');
                    $table->timestamps();

                    $table->unique('employee_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('messaging_policy_acceptances');
            Schema::connection($connection)->dropIfExists('messaging_policies');
            Schema::connection($connection)->dropIfExists('message_reports');
            Schema::connection($connection)->dropIfExists('employee_blocks');
            Schema::connection($connection)->dropIfExists('messages');
            Schema::connection($connection)->dropIfExists('conversation_participants');
            Schema::connection($connection)->dropIfExists('conversations');

            if (Schema::connection($connection)->hasColumn('employees', 'messaging_disabled_at')) {
                Schema::connection($connection)->table('employees', function (Blueprint $table) {
                    $table->dropColumn('messaging_disabled_at');
                });
            }
        }
    }
};

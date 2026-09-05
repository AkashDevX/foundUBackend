<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chat help FAQs managed by org admins (shown in the mobile Chat Help screen).
 */
return new class extends Migration
{
    /**
     * @return list<array{label: string, icon: string, question: string, answer: string, keywords: list<string>, sort_order: int}>
     */
    private function demoFaqs(): array
    {
        return [
            [
                'label' => 'Site safety',
                'icon' => 'shield',
                'question' => 'What should I know about site safety?',
                'answer' => 'Always complete your site induction before starting work. Wear the PPE required for your role, stay on designated walkways, and never enter restricted zones without authorization. Report hazards, near-misses, and injuries to your supervisor immediately — don’t wait until end of shift.',
                'keywords' => ['safety', 'ppe', 'hazard', 'induction', 'protective'],
                'sort_order' => 1,
            ],
            [
                'label' => 'Clock in / out',
                'icon' => 'clock',
                'question' => 'How do I clock in and out?',
                'answer' => 'Go to the Home tab and tap the clock button when you arrive on site. You must clock out when you leave. If clock-in fails, you may be outside the geo-fence — move closer to the worksite boundary or speak to your supervisor.',
                'keywords' => ['clock', 'clock in', 'clock out', 'time clock', 'punch'],
                'sort_order' => 2,
            ],
            [
                'label' => 'My shifts',
                'icon' => 'calendar',
                'question' => 'Where can I see my upcoming shifts?',
                'answer' => 'Open the Shifts tab to view your roster, start/end times, and location details. Pull down to refresh if your schedule was recently updated by your employer.',
                'keywords' => ['shift', 'roster', 'schedule', 'upcoming', 'calendar'],
                'sort_order' => 3,
            ],
            [
                'label' => 'Site tasks',
                'icon' => 'clipboard',
                'question' => 'Where do I see my site tasks?',
                'answer' => 'Open the Tasks tab. You will find worksite actions such as induction checklists, equipment inspections, toolbox talks, and hazard reporting. Mark tasks complete once finished — your supervisor can track progress from the office.',
                'keywords' => ['task', 'checklist', 'todo', 'assignment', 'inspection'],
                'sort_order' => 4,
            ],
            [
                'label' => 'Geo-fence',
                'icon' => 'map-pin',
                'question' => 'Why was I clocked out automatically?',
                'answer' => 'CruLynk monitors your location while you are clocked in. If you leave the worksite geo-fence, the app may alert you or auto clock-out depending on your employer’s settings. Stay within the site boundary while on the clock, or clock out before leaving.',
                'keywords' => ['geo', 'geofence', 'location', 'auto clock', 'clocked out', 'boundary'],
                'sort_order' => 5,
            ],
            [
                'label' => 'My profile',
                'icon' => 'user',
                'question' => 'How do I update my profile?',
                'answer' => 'Tap your profile photo in the top-left corner of any tab to open My Profile. From there you can update your contact details, photo, and qualifications. Some fields may require admin approval before they appear on site records.',
                'keywords' => ['profile', 'photo', 'details', 'account', 'qualification'],
                'sort_order' => 6,
            ],
            [
                'label' => 'Report issue',
                'icon' => 'alert-circle',
                'question' => 'How do I report a problem on site?',
                'answer' => 'For non-emergencies, use the Tasks tab or your site’s reporting process to log the issue. For emergencies, follow your site’s emergency procedures and call the appropriate services first — CruLynk is not a replacement for emergency response.',
                'keywords' => ['report', 'issue', 'problem', 'incident', 'emergency'],
                'sort_order' => 7,
            ],
            [
                'label' => 'Breaks',
                'icon' => 'coffee',
                'question' => 'Do I need to clock out for breaks?',
                'answer' => 'This depends on your employer’s policy. Some sites require you to stay clocked in for paid breaks; others ask you to clock out for unpaid meal breaks. Check with your supervisor or site rules if you are unsure.',
                'keywords' => ['break', 'lunch', 'meal', 'rest'],
                'sort_order' => 8,
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('chat_faqs')) {
                Schema::connection($connection)->create('chat_faqs', function (Blueprint $table) {
                    $table->id();
                    $table->string('label', 80);
                    $table->string('icon', 40)->default('help-circle');
                    $table->string('question', 255);
                    $table->text('answer');
                    $table->json('keywords')->nullable();
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->boolean('is_active')->default(true);
                    $table->string('created_by', 160)->nullable();
                    $table->timestamps();

                    $table->index(['is_active', 'sort_order']);
                });
            }

            if (DB::connection($connection)->table('chat_faqs')->exists()) {
                continue;
            }

            foreach ($this->demoFaqs() as $faq) {
                DB::connection($connection)->table('chat_faqs')->insert([
                    'label' => $faq['label'],
                    'icon' => $faq['icon'],
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                    'keywords' => json_encode($faq['keywords']),
                    'sort_order' => $faq['sort_order'],
                    'is_active' => true,
                    'created_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('chat_faqs');
        }
    }
};

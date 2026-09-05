<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Employee;
use App\Models\EmployeeBlock;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\MessagingPolicy;
use App\Models\MessagingPolicyAcceptance;
use App\Support\MessagingPolicyText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessagingService
{
    /** @var array<string, string> */
    private array $displayNameCache = [];

    public function __construct(
        private readonly ChatAttachmentStorage $attachments,
    ) {}

    public function currentPolicy(): MessagingPolicy
    {
        $policy = MessagingPolicy::query()->orderBy('id')->first();
        if ($policy) {
            if (! is_string($policy->content) || trim($policy->content) === '') {
                $policy->content = $this->defaultMessagingPolicyContent();
                $policy->save();
            }

            return $policy;
        }

        $policy = new MessagingPolicy;
        $policy->content = $this->defaultMessagingPolicyContent();
        $policy->version = 1;
        $policy->last_updated_on = now()->toDateString();
        $policy->save();

        return $policy;
    }

    /**
     * @return array{content: string, version: int, last_updated_on: string|null, accepted: bool}
     */
    public function policyStatusForEmployee(Employee $employee): array
    {
        $this->assertEmployeeCanUseMessaging($employee);
        $policy = $this->currentPolicy();

        return [
            'content' => (string) $policy->content,
            'version' => (int) $policy->version,
            'last_updated_on' => $policy->last_updated_on?->toDateString(),
            'accepted' => $this->employeeHasAcceptedPolicy($employee),
        ];
    }

    public function acceptPolicy(Employee $employee): MessagingPolicyAcceptance
    {
        $this->assertEmployeeCanUseMessaging($employee);
        $policy = $this->currentPolicy();

        return MessagingPolicyAcceptance::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'policy_version' => $policy->version,
                'accepted_at' => now(),
            ],
        );
    }

    public function employeeHasAcceptedPolicy(Employee $employee): bool
    {
        $policy = $this->currentPolicy();
        $acceptance = MessagingPolicyAcceptance::query()
            ->where('employee_id', $employee->id)
            ->first();

        return $acceptance !== null && (int) $acceptance->policy_version >= (int) $policy->version;
    }

    public function reportMessageAsEmployee(Employee $employee, Message $message, string $reason): MessageReport
    {
        $this->assertEmployeeCanUseMessaging($employee);
        $this->assertPolicyAccepted($employee);

        $conversation = $message->conversation
            ?? $this->findConversationOrFail((int) $message->conversation_id);

        $this->assertActiveParticipant(
            $conversation,
            ConversationParticipant::TYPE_EMPLOYEE,
            $employee->id,
        );

        if (
            $message->sender_type === ConversationParticipant::TYPE_EMPLOYEE
            && (int) $message->sender_id === (int) $employee->id
        ) {
            throw ValidationException::withMessages(['message_id' => 'You cannot report your own message.']);
        }

        if ($message->message_type === Message::TYPE_SYSTEM) {
            throw ValidationException::withMessages(['message_id' => 'System messages cannot be reported.']);
        }

        $existing = MessageReport::query()
            ->where('message_id', $message->id)
            ->where('reporter_type', ConversationParticipant::TYPE_EMPLOYEE)
            ->where('reporter_id', $employee->id)
            ->where('status', MessageReport::STATUS_OPEN)
            ->exists();
        if ($existing) {
            throw ValidationException::withMessages(['message_id' => 'You already reported this message.']);
        }

        return $this->reportMessage(
            ConversationParticipant::TYPE_EMPLOYEE,
            $employee->id,
            $message,
            $reason,
        );
    }

    /**
     * Report the latest eligible peer message in a conversation (user report shortcut).
     */
    public function reportPeerInConversationAsEmployee(
        Employee $employee,
        Conversation $conversation,
        string $reason,
    ): MessageReport {
        $this->assertEmployeeCanUseMessaging($employee);
        $this->assertPolicyAccepted($employee);
        $this->assertActiveParticipant(
            $conversation,
            ConversationParticipant::TYPE_EMPLOYEE,
            $employee->id,
        );

        $message = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('deleted_at')
            ->where('message_type', '!=', Message::TYPE_SYSTEM)
            ->where(function ($q) use ($employee) {
                $q->where('sender_type', '!=', ConversationParticipant::TYPE_EMPLOYEE)
                    ->orWhere('sender_id', '!=', $employee->id);
            })
            ->orderByDesc('id')
            ->first();

        if (! $message) {
            throw ValidationException::withMessages([
                'conversation_id' => 'There is no message to report yet. Report a specific message after it arrives.',
            ]);
        }

        return $this->reportMessageAsEmployee($employee, $message, $reason);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOpenMessageReportsForAdmin(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        $reports = MessageReport::query()
            ->with(['message' => fn ($q) => $q->withTrashed()])
            ->where('status', MessageReport::STATUS_OPEN)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $reports->map(function (MessageReport $report) {
            $message = $report->message;

            return [
                'id' => $report->id,
                'reason' => $report->reason,
                'status' => $report->status,
                'created_at' => $report->created_at?->toIso8601String(),
                'message' => $message ? [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'body' => $message->body,
                    'message_type' => $message->message_type,
                    'sender_display_name' => $this->senderDisplayName(
                        (string) $message->sender_type,
                        (int) $message->sender_id,
                    ),
                    'created_at' => $message->created_at?->toIso8601String(),
                    'deleted' => $message->trashed(),
                ] : null,
                'reporter_display_name' => $this->senderDisplayName(
                    (string) $report->reporter_type,
                    (int) $report->reporter_id,
                ),
            ];
        })->values()->all();
    }

    public function resolveMessageReport(int $reportId, string $status, ?string $notes = null): MessageReport
    {
        if (! in_array($status, [MessageReport::STATUS_RESOLVED, MessageReport::STATUS_DISMISSED], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid report status.']);
        }

        $report = MessageReport::query()->find($reportId);
        if (! $report) {
            throw new NotFoundHttpException('Report not found.');
        }

        $report->status = $status;
        $report->reviewer_notes = $notes !== null && trim($notes) !== '' ? trim($notes) : $report->reviewer_notes;
        $report->reviewed_at = now();
        $report->save();

        return $report;
    }

    private function defaultMessagingPolicyContent(): string
    {
        return MessagingPolicyText::defaultContent();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function directoryForEmployee(Employee $viewer): array
    {
        $this->assertEmployeeCanUseMessaging($viewer);

        $blockedIds = $this->blockedPeerIds($viewer->id);

        $employees = Employee::query()
            ->where('employment_status', 'active')
            ->whereNull('messaging_disabled_at')
            ->where('id', '!=', $viewer->id)
            ->when($blockedIds !== [], fn ($q) => $q->whereNotIn('id', $blockedIds))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'public_id', 'first_name', 'last_name', 'job_title', 'department']);

        $items = [
            [
                'type' => ConversationParticipant::TYPE_COMPANY_ADMIN,
                'id' => ConversationParticipant::COMPANY_ADMIN_ID,
                'public_id' => null,
                'display_name' => 'Organization Admin',
                'subtitle' => 'Company administrators',
            ],
        ];

        foreach ($employees as $employee) {
            $items[] = [
                'type' => ConversationParticipant::TYPE_EMPLOYEE,
                'id' => $employee->id,
                'public_id' => $employee->public_id,
                'display_name' => $this->employeeDisplayName($employee),
                'subtitle' => trim(implode(' · ', array_filter([
                    is_string($employee->job_title) ? $employee->job_title : null,
                    is_string($employee->department) ? $employee->department : null,
                ]))) ?: null,
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function directoryForAdmin(): array
    {
        $employees = Employee::query()
            ->where('employment_status', 'active')
            ->whereNull('messaging_disabled_at')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'public_id', 'first_name', 'last_name', 'job_title', 'department', 'email']);

        return $employees->map(fn (Employee $employee) => [
            'type' => ConversationParticipant::TYPE_EMPLOYEE,
            'id' => $employee->id,
            'public_id' => $employee->public_id,
            'display_name' => $this->employeeDisplayName($employee),
            'subtitle' => $employee->email,
            'email' => $employee->email,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inboxForEmployee(Employee $employee): array
    {
        $this->assertEmployeeCanUseMessaging($employee);

        $conversationIds = ConversationParticipant::query()
            ->where('participant_type', ConversationParticipant::TYPE_EMPLOYEE)
            ->where('participant_id', $employee->id)
            ->whereNull('left_at')
            ->pluck('conversation_id');

        if ($conversationIds->isEmpty()) {
            return [];
        }

        $conversations = Conversation::query()
            ->whereIn('id', $conversationIds)
            ->with([
                'activeParticipants',
                'latestMessage' => fn ($q) => $q->whereNull('deleted_at'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $participantRows = ConversationParticipant::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('participant_type', ConversationParticipant::TYPE_EMPLOYEE)
            ->where('participant_id', $employee->id)
            ->get()
            ->keyBy('conversation_id');

        $items = [];
        foreach ($conversations as $conversation) {
            $self = $participantRows->get($conversation->id);
            $items[] = $this->serializeConversationSummary(
                $conversation,
                $self?->last_read_at,
                ConversationParticipant::TYPE_EMPLOYEE,
                $employee->id,
            );
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inboxForAdmin(): array
    {
        $conversationIds = ConversationParticipant::query()
            ->where('participant_type', ConversationParticipant::TYPE_COMPANY_ADMIN)
            ->where('participant_id', ConversationParticipant::COMPANY_ADMIN_ID)
            ->whereNull('left_at')
            ->pluck('conversation_id');

        if ($conversationIds->isEmpty()) {
            return [];
        }

        $conversations = Conversation::query()
            ->whereIn('id', $conversationIds)
            ->with([
                'activeParticipants',
                'latestMessage' => fn ($q) => $q->whereNull('deleted_at'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $participantRows = ConversationParticipant::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('participant_type', ConversationParticipant::TYPE_COMPANY_ADMIN)
            ->where('participant_id', ConversationParticipant::COMPANY_ADMIN_ID)
            ->get()
            ->keyBy('conversation_id');

        $items = [];
        foreach ($conversations as $conversation) {
            $self = $participantRows->get($conversation->id);
            $items[] = $this->serializeConversationSummary(
                $conversation,
                $self?->last_read_at,
                ConversationParticipant::TYPE_COMPANY_ADMIN,
                ConversationParticipant::COMPANY_ADMIN_ID,
            );
        }

        return $items;
    }

    public function openOrCreateDirectForEmployee(Employee $employee, string $peerType, int $peerId): Conversation
    {
        $this->assertEmployeeCanUseMessaging($employee);
        $this->assertPolicyAccepted($employee);

        if ($peerType === ConversationParticipant::TYPE_EMPLOYEE) {
            if ($peerId === (int) $employee->id) {
                throw ValidationException::withMessages(['peer_id' => 'You cannot message yourself.']);
            }
            $peer = Employee::query()
                ->where('id', $peerId)
                ->where('employment_status', 'active')
                ->whereNull('messaging_disabled_at')
                ->first();
            if (! $peer) {
                throw ValidationException::withMessages(['peer_id' => 'Employee not found or unavailable.']);
            }
        } elseif ($peerType === ConversationParticipant::TYPE_COMPANY_ADMIN) {
            $peerId = ConversationParticipant::COMPANY_ADMIN_ID;
        } else {
            throw ValidationException::withMessages(['peer_type' => 'Invalid peer type.']);
        }

        $existing = $this->findDirectConversation(
            ConversationParticipant::TYPE_EMPLOYEE,
            $employee->id,
            $peerType,
            $peerId,
        );
        if ($existing) {
            return $existing;
        }

        if (
            $peerType === ConversationParticipant::TYPE_EMPLOYEE
            && $this->isBlockedEitherWay($employee->id, $peerId)
        ) {
            throw ValidationException::withMessages(['peer_id' => 'Messaging is blocked with this employee.']);
        }

        return DB::transaction(function () use ($employee, $peerType, $peerId) {
            $conversation = Conversation::query()->create([
                'type' => Conversation::TYPE_DIRECT,
                'title' => null,
                'created_by_type' => ConversationParticipant::TYPE_EMPLOYEE,
                'created_by_id' => $employee->id,
                'last_message_at' => null,
            ]);

            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'participant_type' => ConversationParticipant::TYPE_EMPLOYEE,
                'participant_id' => $employee->id,
            ]);
            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'participant_type' => $peerType,
                'participant_id' => $peerId,
            ]);

            return $conversation->fresh(['activeParticipants']);
        });
    }

    public function openOrCreateDirectForAdmin(int $employeeId): Conversation
    {
        $peer = Employee::query()
            ->where('id', $employeeId)
            ->where('employment_status', 'active')
            ->whereNull('messaging_disabled_at')
            ->first();
        if (! $peer) {
            throw ValidationException::withMessages(['employee_id' => 'Employee not found or unavailable.']);
        }

        $existing = $this->findDirectConversation(
            ConversationParticipant::TYPE_COMPANY_ADMIN,
            ConversationParticipant::COMPANY_ADMIN_ID,
            ConversationParticipant::TYPE_EMPLOYEE,
            $employeeId,
        );
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($employeeId) {
            $conversation = Conversation::query()->create([
                'type' => Conversation::TYPE_DIRECT,
                'title' => null,
                'created_by_type' => ConversationParticipant::TYPE_COMPANY_ADMIN,
                'created_by_id' => ConversationParticipant::COMPANY_ADMIN_ID,
                'last_message_at' => null,
            ]);

            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'participant_type' => ConversationParticipant::TYPE_COMPANY_ADMIN,
                'participant_id' => ConversationParticipant::COMPANY_ADMIN_ID,
            ]);
            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'participant_type' => ConversationParticipant::TYPE_EMPLOYEE,
                'participant_id' => $employeeId,
            ]);

            return $conversation->fresh(['activeParticipants']);
        });
    }

    /**
     * @param  list<int>  $memberEmployeeIds
     */
    public function createGroupForEmployee(
        Employee $employee,
        string $title,
        array $memberEmployeeIds,
        bool $includeAdmin = false,
    ): Conversation {
        $this->assertEmployeeCanUseMessaging($employee);
        $this->assertPolicyAccepted($employee);

        $title = trim($title);
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Group title is required.']);
        }

        $memberEmployeeIds = array_values(array_unique(array_map('intval', $memberEmployeeIds)));
        $memberEmployeeIds = array_values(array_filter(
            $memberEmployeeIds,
            fn (int $id) => $id !== (int) $employee->id,
        ));

        $blockedIds = $this->blockedPeerIds($employee->id);
        foreach ($memberEmployeeIds as $id) {
            if (in_array($id, $blockedIds, true)) {
                throw ValidationException::withMessages(['member_ids' => 'One or more members are blocked.']);
            }
        }

        $validMembers = Employee::query()
            ->whereIn('id', $memberEmployeeIds)
            ->where('employment_status', 'active')
            ->whereNull('messaging_disabled_at')
            ->pluck('id')
            ->all();

        if (count($validMembers) !== count($memberEmployeeIds)) {
            throw ValidationException::withMessages(['member_ids' => 'One or more members are invalid.']);
        }

        return DB::transaction(function () use ($employee, $title, $validMembers, $includeAdmin) {
            $conversation = Conversation::query()->create([
                'type' => Conversation::TYPE_GROUP,
                'title' => $title,
                'created_by_type' => ConversationParticipant::TYPE_EMPLOYEE,
                'created_by_id' => $employee->id,
                'last_message_at' => now(),
            ]);

            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'participant_type' => ConversationParticipant::TYPE_EMPLOYEE,
                'participant_id' => $employee->id,
                'last_read_at' => now(),
            ]);

            foreach ($validMembers as $memberId) {
                ConversationParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'participant_type' => ConversationParticipant::TYPE_EMPLOYEE,
                    'participant_id' => $memberId,
                ]);
            }

            if ($includeAdmin) {
                ConversationParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'participant_type' => ConversationParticipant::TYPE_COMPANY_ADMIN,
                    'participant_id' => ConversationParticipant::COMPANY_ADMIN_ID,
                ]);
            }

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => ConversationParticipant::TYPE_EMPLOYEE,
                'sender_id' => $employee->id,
                'body' => $this->employeeDisplayName($employee).' created the group.',
                'message_type' => Message::TYPE_SYSTEM,
            ]);

            return $conversation->fresh(['activeParticipants']);
        });
    }

    /**
     * @param  list<int>  $memberEmployeeIds
     */
    public function createGroupForAdmin(string $title, array $memberEmployeeIds): Conversation
    {
        $title = trim($title);
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Group title is required.']);
        }

        $memberEmployeeIds = array_values(array_unique(array_map('intval', $memberEmployeeIds)));
        if ($memberEmployeeIds === []) {
            throw ValidationException::withMessages(['member_ids' => 'Add at least one employee.']);
        }

        $validMembers = Employee::query()
            ->whereIn('id', $memberEmployeeIds)
            ->where('employment_status', 'active')
            ->whereNull('messaging_disabled_at')
            ->pluck('id')
            ->all();

        if (count($validMembers) !== count($memberEmployeeIds)) {
            throw ValidationException::withMessages(['member_ids' => 'One or more members are invalid.']);
        }

        return DB::transaction(function () use ($title, $validMembers) {
            $conversation = Conversation::query()->create([
                'type' => Conversation::TYPE_GROUP,
                'title' => $title,
                'created_by_type' => ConversationParticipant::TYPE_COMPANY_ADMIN,
                'created_by_id' => ConversationParticipant::COMPANY_ADMIN_ID,
                'last_message_at' => now(),
            ]);

            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'participant_type' => ConversationParticipant::TYPE_COMPANY_ADMIN,
                'participant_id' => ConversationParticipant::COMPANY_ADMIN_ID,
                'last_read_at' => now(),
            ]);

            foreach ($validMembers as $memberId) {
                ConversationParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'participant_type' => ConversationParticipant::TYPE_EMPLOYEE,
                    'participant_id' => $memberId,
                ]);
            }

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => ConversationParticipant::TYPE_COMPANY_ADMIN,
                'sender_id' => ConversationParticipant::COMPANY_ADMIN_ID,
                'body' => 'Organization Admin created the group.',
                'message_type' => Message::TYPE_SYSTEM,
            ]);

            return $conversation->fresh(['activeParticipants']);
        });
    }

    /**
     * @param  list<int>  $addEmployeeIds
     * @param  list<int>  $removeEmployeeIds
     */
    public function updateMembersForEmployee(
        Employee $employee,
        Conversation $conversation,
        array $addEmployeeIds = [],
        array $removeEmployeeIds = [],
        ?bool $includeAdmin = null,
    ): Conversation {
        $this->assertEmployeeCanUseMessaging($employee);
        $this->assertActiveParticipant(
            $conversation,
            ConversationParticipant::TYPE_EMPLOYEE,
            $employee->id,
        );

        if ($conversation->type !== Conversation::TYPE_GROUP) {
            throw ValidationException::withMessages(['conversation' => 'Only groups support member changes.']);
        }

        $isCreator = $conversation->created_by_type === ConversationParticipant::TYPE_EMPLOYEE
            && (int) $conversation->created_by_id === (int) $employee->id;
        if (! $isCreator) {
            throw new AccessDeniedHttpException('Only the group creator can manage members.');
        }

        return $this->applyMemberUpdates($conversation, $addEmployeeIds, $removeEmployeeIds, $includeAdmin);
    }

    /**
     * @param  list<int>  $addEmployeeIds
     * @param  list<int>  $removeEmployeeIds
     */
    public function updateMembersForAdmin(
        Conversation $conversation,
        array $addEmployeeIds = [],
        array $removeEmployeeIds = [],
    ): Conversation {
        $this->assertActiveParticipant(
            $conversation,
            ConversationParticipant::TYPE_COMPANY_ADMIN,
            ConversationParticipant::COMPANY_ADMIN_ID,
        );

        if ($conversation->type !== Conversation::TYPE_GROUP) {
            throw ValidationException::withMessages(['conversation' => 'Only groups support member changes.']);
        }

        return $this->applyMemberUpdates($conversation, $addEmployeeIds, $removeEmployeeIds, null);
    }

    /**
     * @return array{messages: list<array<string, mixed>>, conversation: array<string, mixed>}
     */
    public function listMessagesForEmployee(
        Employee $employee,
        Conversation $conversation,
        ?int $afterId = null,
        ?int $beforeId = null,
        int $limit = 50,
    ): array {
        $this->assertEmployeeCanUseMessaging($employee);
        $participant = $this->assertActiveParticipant(
            $conversation,
            ConversationParticipant::TYPE_EMPLOYEE,
            $employee->id,
        );

        $messages = $this->fetchMessages($conversation, $afterId, $beforeId, $limit);
        $conversation->loadMissing('activeParticipants');
        $this->primeDisplayNames($messages, $conversation->activeParticipants);

        if ($afterId === null && $beforeId === null) {
            $participant->last_read_at = now();
            $participant->save();
        } elseif ($messages->isNotEmpty()) {
            $maxId = (int) $messages->max('id');
            $latest = Message::query()->where('conversation_id', $conversation->id)->whereNull('deleted_at')->max('id');
            if ($maxId >= (int) $latest) {
                $participant->last_read_at = now();
                $participant->save();
            }
        }

        return [
            'conversation' => $this->serializeConversationSummary(
                $conversation,
                $participant->last_read_at,
                ConversationParticipant::TYPE_EMPLOYEE,
                $employee->id,
            ),
            'messages' => $messages->map(fn (Message $m) => $this->serializeMessage(
                $m,
                ConversationParticipant::TYPE_EMPLOYEE,
                $employee->id,
            ))->values()->all(),
        ];
    }

    /**
     * @return array{messages: list<array<string, mixed>>, conversation: array<string, mixed>}
     */
    public function listMessagesForAdmin(
        Conversation $conversation,
        ?int $afterId = null,
        ?int $beforeId = null,
        int $limit = 50,
    ): array {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('participant_type', ConversationParticipant::TYPE_COMPANY_ADMIN)
            ->where('participant_id', ConversationParticipant::COMPANY_ADMIN_ID)
            ->whereNull('left_at')
            ->first();

        // Admins may open any org conversation for safety/report review, even when they are
        // not a participant (e.g. employee-to-employee chats).
        $isParticipant = $participant !== null;

        $messages = $this->fetchMessages($conversation, $afterId, $beforeId, $limit);
        $conversation->loadMissing('activeParticipants');
        $this->primeDisplayNames($messages, $conversation->activeParticipants);

        if ($isParticipant) {
            if ($afterId === null && $beforeId === null) {
                $participant->last_read_at = now();
                $participant->save();
            } elseif ($messages->isNotEmpty()) {
                $maxId = (int) $messages->max('id');
                $latest = Message::query()->where('conversation_id', $conversation->id)->whereNull('deleted_at')->max('id');
                if ($maxId >= (int) $latest) {
                    $participant->last_read_at = now();
                    $participant->save();
                }
            }
        }

        $summary = $this->serializeConversationSummary(
            $conversation,
            $participant?->last_read_at,
            ConversationParticipant::TYPE_COMPANY_ADMIN,
            ConversationParticipant::COMPANY_ADMIN_ID,
        );
        $summary['can_send'] = $isParticipant && (bool) ($summary['can_send'] ?? false);
        $summary['is_moderation_view'] = ! $isParticipant;

        return [
            'conversation' => $summary,
            'messages' => $messages->map(fn (Message $m) => $this->serializeMessage(
                $m,
                ConversationParticipant::TYPE_COMPANY_ADMIN,
                ConversationParticipant::COMPANY_ADMIN_ID,
            ))->values()->all(),
        ];
    }

    public function sendMessageAsEmployee(
        Employee $employee,
        Conversation $conversation,
        ?string $body,
        ?UploadedFile $attachment,
        string $companySlug,
    ): Message {
        $this->assertEmployeeCanUseMessaging($employee);
        $this->assertPolicyAccepted($employee);
        $this->assertActiveParticipant(
            $conversation,
            ConversationParticipant::TYPE_EMPLOYEE,
            $employee->id,
        );

        if ($conversation->type === Conversation::TYPE_DIRECT) {
            $peerEmployeeId = $this->directPeerEmployeeId($conversation, $employee->id);
            if ($peerEmployeeId !== null && $this->isBlockedEitherWay($employee->id, $peerEmployeeId)) {
                throw new AccessDeniedHttpException('Messaging is blocked with this employee.');
            }
        }

        return $this->createMessage(
            $conversation,
            ConversationParticipant::TYPE_EMPLOYEE,
            $employee->id,
            $body,
            $attachment,
            $companySlug,
        );
    }

    public function sendMessageAsAdmin(
        Conversation $conversation,
        ?string $body,
        ?UploadedFile $attachment,
        string $companySlug,
    ): Message {
        $this->assertActiveParticipant(
            $conversation,
            ConversationParticipant::TYPE_COMPANY_ADMIN,
            ConversationParticipant::COMPANY_ADMIN_ID,
        );

        return $this->createMessage(
            $conversation,
            ConversationParticipant::TYPE_COMPANY_ADMIN,
            ConversationParticipant::COMPANY_ADMIN_ID,
            $body,
            $attachment,
            $companySlug,
        );
    }

    public function blockEmployee(Employee $blocker, int $blockedEmployeeId): void
    {
        $this->assertEmployeeCanUseMessaging($blocker);

        if ($blockedEmployeeId === (int) $blocker->id) {
            throw ValidationException::withMessages(['employee_id' => 'You cannot block yourself.']);
        }

        $target = Employee::query()->where('id', $blockedEmployeeId)->first();
        if (! $target) {
            throw ValidationException::withMessages(['employee_id' => 'Employee not found.']);
        }

        EmployeeBlock::query()->firstOrCreate([
            'blocker_employee_id' => $blocker->id,
            'blocked_employee_id' => $blockedEmployeeId,
        ]);
    }

    public function unblockEmployee(Employee $blocker, int $blockedEmployeeId): void
    {
        EmployeeBlock::query()
            ->where('blocker_employee_id', $blocker->id)
            ->where('blocked_employee_id', $blockedEmployeeId)
            ->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBlocks(Employee $employee): array
    {
        $blocks = EmployeeBlock::query()
            ->where('blocker_employee_id', $employee->id)
            ->with('blocked:id,public_id,first_name,last_name')
            ->orderByDesc('id')
            ->get();

        return $blocks->map(function (EmployeeBlock $block) {
            $blocked = $block->blocked;

            return [
                'employee_id' => $block->blocked_employee_id,
                'public_id' => $blocked?->public_id,
                'display_name' => $blocked ? $this->employeeDisplayName($blocked) : 'Unknown',
                'blocked_at' => $block->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    public function reportMessage(
        string $reporterType,
        int $reporterId,
        Message $message,
        string $reason,
    ): MessageReport {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Please provide a reason.']);
        }

        if ($message->trashed()) {
            throw ValidationException::withMessages(['message_id' => 'Message is no longer available.']);
        }

        return MessageReport::query()->create([
            'message_id' => $message->id,
            'reporter_type' => $reporterType,
            'reporter_id' => $reporterId,
            'reason' => $reason,
            'status' => MessageReport::STATUS_OPEN,
        ]);
    }

    public function softDeleteMessage(Message $message): void
    {
        $message->delete();
    }

    public function setEmployeeMessagingDisabled(Employee $employee, bool $disabled): void
    {
        $employee->messaging_disabled_at = $disabled ? now() : null;
        $employee->save();
    }

    public function findConversationOrFail(int $id): Conversation
    {
        $conversation = Conversation::query()->find($id);
        if (! $conversation) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        return $conversation;
    }

    public function findMessageOrFail(int $id): Message
    {
        $message = Message::query()->find($id);
        if (! $message) {
            throw new NotFoundHttpException('Message not found.');
        }

        return $message;
    }

    public function assertEmployeeCanAccessAttachment(Employee $employee, Message $message): void
    {
        $this->assertActiveParticipant(
            $message->conversation ?? $this->findConversationOrFail((int) $message->conversation_id),
            ConversationParticipant::TYPE_EMPLOYEE,
            $employee->id,
        );
    }

    public function assertEmployeeParticipant(Employee $employee, Conversation $conversation): void
    {
        $this->assertActiveParticipant(
            $conversation,
            ConversationParticipant::TYPE_EMPLOYEE,
            $employee->id,
        );
    }

    public function assertAdminCanAccessAttachment(Message $message): void
    {
        // Org admins can open attachments for any message in the tenant (inbox + report review).
        $this->findConversationOrFail((int) $message->conversation_id);
    }

    public function openReportCount(): int
    {
        return MessageReport::query()->where('status', MessageReport::STATUS_OPEN)->count();
    }

    public function unreadAdminConversationCount(): int
    {
        $rows = ConversationParticipant::query()
            ->where('participant_type', ConversationParticipant::TYPE_COMPANY_ADMIN)
            ->where('participant_id', ConversationParticipant::COMPANY_ADMIN_ID)
            ->whereNull('left_at')
            ->get(['conversation_id', 'last_read_at']);

        $count = 0;
        foreach ($rows as $row) {
            $query = Message::query()
                ->where('conversation_id', $row->conversation_id)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->where('sender_type', '!=', ConversationParticipant::TYPE_COMPANY_ADMIN)
                        ->orWhere('sender_id', '!=', ConversationParticipant::COMPANY_ADMIN_ID);
                });
            if ($row->last_read_at) {
                $query->where('created_at', '>', $row->last_read_at);
            }
            if ($query->exists()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeMessage(Message $message, ?string $viewerType = null, ?int $viewerId = null): array
    {
        $isMine = false;
        if ($viewerType !== null && $viewerId !== null) {
            $isMine = $message->sender_type === $viewerType && (int) $message->sender_id === $viewerId;
        }

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sender_display_name' => $this->senderDisplayName($message->sender_type, (int) $message->sender_id),
            'body' => $message->body,
            'message_type' => $message->message_type,
            'attachment' => $message->hasAttachment() ? [
                'id' => $message->id,
                'name' => $message->attachment_name,
                'mime' => $message->attachment_mime,
                'size' => $message->attachment_size,
            ] : null,
            'created_at' => $message->created_at?->toIso8601String(),
            'deleted' => $message->trashed(),
            'is_mine' => $isMine,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeConversationSummary(
        Conversation $conversation,
        mixed $lastReadAt,
        string $viewerType,
        int $viewerId,
    ): array {
        $participants = $conversation->relationLoaded('activeParticipants')
            ? $conversation->activeParticipants
            : $conversation->activeParticipants()->get();

        $latest = $conversation->relationLoaded('latestMessage')
            ? $conversation->latestMessage
            : $conversation->messages()->whereNull('deleted_at')->latest('id')->first();

        if ($this->isSuppressedBlockSystemMessage($latest)) {
            $latest = $conversation->messages()
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->where('message_type', '!=', Message::TYPE_SYSTEM)
                        ->orWhereNotIn('body', $this->suppressedBlockSystemBodies());
                })
                ->latest('id')
                ->first();
        }

        $unread = 0;
        if ($latest) {
            $unreadQuery = Message::query()
                ->where('conversation_id', $conversation->id)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($viewerType, $viewerId) {
                    $q->where('sender_type', '!=', $viewerType)
                        ->orWhere('sender_id', '!=', $viewerId);
                });
            if ($lastReadAt) {
                $unreadQuery->where('created_at', '>', $lastReadAt);
            }
            $unread = $unreadQuery->count();
        }

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $this->conversationTitle($conversation, $participants, $viewerType, $viewerId),
            'participants' => $participants->map(fn (ConversationParticipant $p) => [
                'type' => $p->participant_type,
                'id' => $p->participant_id,
                'display_name' => $this->senderDisplayName($p->participant_type, (int) $p->participant_id),
            ])->values()->all(),
            'peer_employee_id' => $viewerType === ConversationParticipant::TYPE_EMPLOYEE
                ? $this->directPeerEmployeeId($conversation, $viewerId)
                : null,
            'block_status' => $this->blockStatusForViewer($conversation, $viewerType, $viewerId),
            'can_send' => $this->canSendInConversation($conversation, $viewerType, $viewerId),
            'last_message' => $latest && ! $latest->trashed() ? [
                'id' => $latest->id,
                'body' => $latest->body,
                'message_type' => $latest->message_type,
                'sender_display_name' => $this->senderDisplayName($latest->sender_type, (int) $latest->sender_id),
                'created_at' => $latest->created_at?->toIso8601String(),
            ] : null,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => $unread,
        ];
    }

    private function createMessage(
        Conversation $conversation,
        string $senderType,
        int $senderId,
        ?string $body,
        ?UploadedFile $attachment,
        string $companySlug,
    ): Message {
        $body = is_string($body) ? trim($body) : '';
        if ($body === '' && $attachment === null) {
            throw ValidationException::withMessages(['body' => 'Message text or an attachment is required.']);
        }

        $attachmentMeta = null;
        if ($attachment !== null) {
            $attachmentMeta = $this->attachments->store($attachment, $companySlug, (int) $conversation->id);
        }

        $messageType = Message::TYPE_TEXT;
        if ($attachmentMeta !== null) {
            $messageType = $attachmentMeta['message_type'];
        }

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'body' => $body !== '' ? $body : null,
            'message_type' => $messageType,
            'attachment_path' => $attachmentMeta['path'] ?? null,
            'attachment_mime' => $attachmentMeta['mime'] ?? null,
            'attachment_name' => $attachmentMeta['name'] ?? null,
            'attachment_size' => $attachmentMeta['size'] ?? null,
        ]);

        $conversation->last_message_at = now();
        $conversation->save();

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('participant_type', $senderType)
            ->where('participant_id', $senderId)
            ->update(['last_read_at' => now()]);

        $this->notifyNewChatMessage($conversation, $message, $senderType, $senderId);

        return $message;
    }

    /**
     * Best-effort FCM wake-up for other employee participants. Never fails the send.
     */
    private function notifyNewChatMessage(
        Conversation $conversation,
        Message $message,
        string $senderType,
        int $senderId,
    ): void {
        try {
            /** @var FcmPushService $fcm */
            $fcm = app(FcmPushService::class);
            if (! $fcm->isEnabled()) {
                return;
            }

            $recipientIds = ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('participant_type', ConversationParticipant::TYPE_EMPLOYEE)
                ->whereNull('left_at')
                ->pluck('participant_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($senderType === ConversationParticipant::TYPE_EMPLOYEE) {
                $recipientIds = array_values(array_filter(
                    $recipientIds,
                    fn (int $id) => $id !== $senderId,
                ));
            }

            if ($recipientIds === []) {
                return;
            }

            $eligible = Employee::query()
                ->whereIn('id', $recipientIds)
                ->where('employment_status', 'active')
                ->whereNull('messaging_disabled_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (
                $conversation->type === Conversation::TYPE_DIRECT
                && $senderType === ConversationParticipant::TYPE_EMPLOYEE
            ) {
                $eligible = array_values(array_filter(
                    $eligible,
                    fn (int $id) => ! $this->isBlockedEitherWay($senderId, $id),
                ));
            }

            if ($eligible === []) {
                return;
            }

            $senderName = $this->senderDisplayName($senderType, $senderId);
            $isGroup = $conversation->type === Conversation::TYPE_GROUP;
            $groupTitle = is_string($conversation->title) ? trim($conversation->title) : '';
            $title = ($isGroup && $groupTitle !== '') ? $groupTitle : $senderName;

            $textBody = is_string($message->body) ? trim($message->body) : '';
            if ($message->message_type === Message::TYPE_IMAGE) {
                $body = $isGroup ? "{$senderName} sent a photo" : 'Sent a photo';
            } elseif ($message->message_type === Message::TYPE_FILE) {
                $body = $isGroup ? "{$senderName} sent a file" : 'Sent a file';
            } elseif ($textBody !== '') {
                $preview = mb_strlen($textBody) > 120
                    ? mb_substr($textBody, 0, 117).'...'
                    : $textBody;
                $body = $isGroup ? "{$senderName}: {$preview}" : $preview;
            } else {
                $body = $isGroup ? "{$senderName} sent a message" : 'Sent a message';
            }

            $fcm->sendToEmployees($eligible, [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'chat_message',
                    'conversation_id' => (string) $conversation->id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Chat FCM notify failed: '.$e->getMessage());
        }
    }

    /**
     * @return Collection<int, Message>
     */
    private function fetchMessages(
        Conversation $conversation,
        ?int $afterId,
        ?int $beforeId,
        int $limit,
    ): Collection {
        $limit = max(1, min(100, $limit));

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('message_type', '!=', Message::TYPE_SYSTEM)
                    ->orWhereNotIn('body', $this->suppressedBlockSystemBodies());
            });

        if ($afterId !== null) {
            $query->where('id', '>', $afterId)->orderBy('id')->limit($limit);

            return $query->get();
        }

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId)->orderByDesc('id')->limit($limit);

            return $query->get()->sortBy('id')->values();
        }

        $query->orderByDesc('id')->limit($limit);

        return $query->get()->sortBy('id')->values();
    }

    /**
     * @param  list<int>  $addEmployeeIds
     * @param  list<int>  $removeEmployeeIds
     */
    private function applyMemberUpdates(
        Conversation $conversation,
        array $addEmployeeIds,
        array $removeEmployeeIds,
        ?bool $includeAdmin,
    ): Conversation {
        $addEmployeeIds = array_values(array_unique(array_map('intval', $addEmployeeIds)));
        $removeEmployeeIds = array_values(array_unique(array_map('intval', $removeEmployeeIds)));

        DB::transaction(function () use ($conversation, $addEmployeeIds, $removeEmployeeIds, $includeAdmin) {
            if ($addEmployeeIds !== []) {
                $valid = Employee::query()
                    ->whereIn('id', $addEmployeeIds)
                    ->where('employment_status', 'active')
                    ->whereNull('messaging_disabled_at')
                    ->pluck('id')
                    ->all();

                foreach ($valid as $memberId) {
                    $existing = ConversationParticipant::query()
                        ->where('conversation_id', $conversation->id)
                        ->where('participant_type', ConversationParticipant::TYPE_EMPLOYEE)
                        ->where('participant_id', $memberId)
                        ->first();

                    if ($existing) {
                        if ($existing->left_at !== null) {
                            $existing->left_at = null;
                            $existing->save();
                        }
                    } else {
                        ConversationParticipant::query()->create([
                            'conversation_id' => $conversation->id,
                            'participant_type' => ConversationParticipant::TYPE_EMPLOYEE,
                            'participant_id' => $memberId,
                        ]);
                    }
                }
            }

            foreach ($removeEmployeeIds as $memberId) {
                ConversationParticipant::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('participant_type', ConversationParticipant::TYPE_EMPLOYEE)
                    ->where('participant_id', $memberId)
                    ->whereNull('left_at')
                    ->update(['left_at' => now()]);
            }

            if ($includeAdmin === true) {
                $admin = ConversationParticipant::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('participant_type', ConversationParticipant::TYPE_COMPANY_ADMIN)
                    ->where('participant_id', ConversationParticipant::COMPANY_ADMIN_ID)
                    ->first();
                if ($admin) {
                    $admin->left_at = null;
                    $admin->save();
                } else {
                    ConversationParticipant::query()->create([
                        'conversation_id' => $conversation->id,
                        'participant_type' => ConversationParticipant::TYPE_COMPANY_ADMIN,
                        'participant_id' => ConversationParticipant::COMPANY_ADMIN_ID,
                    ]);
                }
            } elseif ($includeAdmin === false) {
                ConversationParticipant::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('participant_type', ConversationParticipant::TYPE_COMPANY_ADMIN)
                    ->where('participant_id', ConversationParticipant::COMPANY_ADMIN_ID)
                    ->whereNull('left_at')
                    ->update(['left_at' => now()]);
            }
        });

        return $conversation->fresh(['activeParticipants']);
    }

    private function findDirectConversation(
        string $typeA,
        int $idA,
        string $typeB,
        int $idB,
    ): ?Conversation {
        $ids = ConversationParticipant::query()
            ->where('participant_type', $typeA)
            ->where('participant_id', $idA)
            ->whereNull('left_at')
            ->pluck('conversation_id');

        if ($ids->isEmpty()) {
            return null;
        }

        $matchIds = ConversationParticipant::query()
            ->whereIn('conversation_id', $ids)
            ->where('participant_type', $typeB)
            ->where('participant_id', $idB)
            ->whereNull('left_at')
            ->pluck('conversation_id');

        if ($matchIds->isEmpty()) {
            return null;
        }

        return Conversation::query()
            ->whereIn('id', $matchIds)
            ->where('type', Conversation::TYPE_DIRECT)
            ->with('activeParticipants')
            ->first();
    }

    private function assertActiveParticipant(
        Conversation $conversation,
        string $type,
        int $id,
    ): ConversationParticipant {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('participant_type', $type)
            ->where('participant_id', $id)
            ->whereNull('left_at')
            ->first();

        if (! $participant) {
            throw new AccessDeniedHttpException('You are not a participant in this conversation.');
        }

        return $participant;
    }

    private function assertEmployeeCanUseMessaging(Employee $employee): void
    {
        if ($employee->employment_status !== 'active') {
            throw new AccessDeniedHttpException('Messaging is only available for active employees.');
        }
        if ($employee->messaging_disabled_at !== null) {
            throw new AccessDeniedHttpException('Messaging has been disabled for your account.');
        }
    }

    private function assertPolicyAccepted(Employee $employee): void
    {
        if (! $this->employeeHasAcceptedPolicy($employee)) {
            throw ValidationException::withMessages([
                'policy' => 'You must accept the messaging policy before sending messages.',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function blockedPeerIds(int $employeeId): array
    {
        $asBlocker = EmployeeBlock::query()
            ->where('blocker_employee_id', $employeeId)
            ->pluck('blocked_employee_id');
        $asBlocked = EmployeeBlock::query()
            ->where('blocked_employee_id', $employeeId)
            ->pluck('blocker_employee_id');

        return $asBlocker->merge($asBlocked)->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    private function isBlockedEitherWay(int $a, int $b): bool
    {
        return EmployeeBlock::query()
            ->where(function ($q) use ($a, $b) {
                $q->where('blocker_employee_id', $a)->where('blocked_employee_id', $b);
            })
            ->orWhere(function ($q) use ($a, $b) {
                $q->where('blocker_employee_id', $b)->where('blocked_employee_id', $a);
            })
            ->exists();
    }

    /**
     * @return 'none'|'blocked_by_me'|'blocked_me'
     */
    private function blockStatusForViewer(Conversation $conversation, string $viewerType, int $viewerId): string
    {
        if (
            $conversation->type !== Conversation::TYPE_DIRECT
            || $viewerType !== ConversationParticipant::TYPE_EMPLOYEE
        ) {
            return 'none';
        }

        $peerId = $this->directPeerEmployeeId($conversation, $viewerId);
        if ($peerId === null) {
            return 'none';
        }

        $iBlockedThem = EmployeeBlock::query()
            ->where('blocker_employee_id', $viewerId)
            ->where('blocked_employee_id', $peerId)
            ->exists();
        if ($iBlockedThem) {
            return 'blocked_by_me';
        }

        $theyBlockedMe = EmployeeBlock::query()
            ->where('blocker_employee_id', $peerId)
            ->where('blocked_employee_id', $viewerId)
            ->exists();
        if ($theyBlockedMe) {
            return 'blocked_me';
        }

        return 'none';
    }

    private function canSendInConversation(Conversation $conversation, string $viewerType, int $viewerId): bool
    {
        return $this->blockStatusForViewer($conversation, $viewerType, $viewerId) === 'none';
    }

    /**
     * @return list<string>
     */
    private function suppressedBlockSystemBodies(): array
    {
        return [
            'Messaging is paused because of a block.',
            'Messaging is available again.',
            'You blocked this conversation. Messaging is paused until someone unblocks.',
        ];
    }

    private function isSuppressedBlockSystemMessage(?Message $message): bool
    {
        if ($message === null || $message->message_type !== Message::TYPE_SYSTEM) {
            return false;
        }

        return in_array((string) $message->body, $this->suppressedBlockSystemBodies(), true);
    }

    private function directPeerEmployeeId(Conversation $conversation, int $viewerEmployeeId): ?int
    {
        $participants = $conversation->relationLoaded('activeParticipants')
            ? $conversation->activeParticipants
            : $conversation->activeParticipants()->get();

        foreach ($participants as $participant) {
            if (
                $participant->participant_type === ConversationParticipant::TYPE_EMPLOYEE
                && (int) $participant->participant_id !== $viewerEmployeeId
            ) {
                return (int) $participant->participant_id;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, ConversationParticipant>|iterable<ConversationParticipant>  $participants
     */
    private function conversationTitle(
        Conversation $conversation,
        $participants,
        string $viewerType,
        int $viewerId,
    ): string {
        if ($conversation->type === Conversation::TYPE_GROUP && is_string($conversation->title) && $conversation->title !== '') {
            return $conversation->title;
        }

        if (
            $viewerType === ConversationParticipant::TYPE_COMPANY_ADMIN
            && $conversation->type === Conversation::TYPE_DIRECT
        ) {
            $employeeNames = [];
            foreach ($participants as $participant) {
                if ($participant->participant_type === ConversationParticipant::TYPE_EMPLOYEE) {
                    $employeeNames[] = $this->senderDisplayName(
                        $participant->participant_type,
                        (int) $participant->participant_id,
                    );
                }
            }
            $employeeNames = array_values(array_unique(array_filter($employeeNames)));
            if (count($employeeNames) >= 2) {
                return $employeeNames[0].' · '.$employeeNames[1];
            }
            if (count($employeeNames) === 1) {
                return $employeeNames[0];
            }
        }

        foreach ($participants as $participant) {
            if (
                $participant->participant_type === $viewerType
                && (int) $participant->participant_id === $viewerId
            ) {
                continue;
            }

            return $this->senderDisplayName($participant->participant_type, (int) $participant->participant_id);
        }

        return $conversation->title ?: 'Conversation';
    }

    private function senderDisplayName(string $type, int $id): string
    {
        if ($type === ConversationParticipant::TYPE_COMPANY_ADMIN) {
            return 'Organization Admin';
        }

        $cacheKey = $type.':'.$id;
        if (array_key_exists($cacheKey, $this->displayNameCache)) {
            return $this->displayNameCache[$cacheKey];
        }

        $employee = Employee::query()->find($id);
        $name = $employee ? $this->employeeDisplayName($employee) : 'Unknown';
        $this->displayNameCache[$cacheKey] = $name;

        return $name;
    }

    /**
     * Prefetch display names so serializing a thread does not N+1 Employee lookups.
     *
     * @param  Collection<int, Message>  $messages
     * @param  Collection<int, ConversationParticipant>|iterable<int, ConversationParticipant>|null  $participants
     */
    private function primeDisplayNames(Collection $messages, mixed $participants = null): void
    {
        $employeeIds = [];

        foreach ($messages as $message) {
            if ($message->sender_type === ConversationParticipant::TYPE_EMPLOYEE) {
                $employeeIds[] = (int) $message->sender_id;
            }
        }

        if ($participants !== null) {
            foreach ($participants as $participant) {
                if ($participant->participant_type === ConversationParticipant::TYPE_EMPLOYEE) {
                    $employeeIds[] = (int) $participant->participant_id;
                }
            }
        }

        $employeeIds = array_values(array_unique(array_filter(
            $employeeIds,
            static fn ($id): bool => is_numeric($id) && (int) $id > 0,
        )));
        $employeeIds = array_map(static fn ($id): int => (int) $id, $employeeIds);
        if ($employeeIds === []) {
            return;
        }

        $missing = [];
        foreach ($employeeIds as $id) {
            $key = ConversationParticipant::TYPE_EMPLOYEE.':'.$id;
            if (! array_key_exists($key, $this->displayNameCache)) {
                $missing[] = $id;
            }
        }
        if ($missing === []) {
            return;
        }

        $employees = Employee::query()->whereIn('id', $missing)->get()->keyBy('id');
        foreach ($missing as $id) {
            $employee = $employees->get($id);
            $this->displayNameCache[ConversationParticipant::TYPE_EMPLOYEE.':'.$id] = $employee
                ? $this->employeeDisplayName($employee)
                : 'Unknown';
        }
    }

    private function employeeDisplayName(Employee $employee): string
    {
        $name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));
        if ($name !== '') {
            return $name;
        }
        if (is_string($employee->full_legal_name) && trim($employee->full_legal_name) !== '') {
            return trim($employee->full_legal_name);
        }

        return $employee->email ?: 'Employee #'.$employee->id;
    }
}

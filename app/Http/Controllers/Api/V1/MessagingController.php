<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatFaq;
use App\Models\Company;
use App\Models\ConversationParticipant;
use App\Models\Employee;
use App\Models\Message;
use App\Services\ChatAttachmentStorage;
use App\Services\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MessagingController extends Controller
{
    public function __construct(
        private readonly MessagingService $messaging,
        private readonly ChatAttachmentStorage $attachments,
    ) {}

    public function faqs(Request $request): JsonResponse
    {
        $faqs = ChatFaq::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ChatFaq $faq) => $faq->toApiArray())
            ->values()
            ->all();

        return response()->json(['faqs' => $faqs]);
    }

    public function directory(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return response()->json([
            'items' => $this->messaging->directoryForEmployee($employee),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return response()->json([
            'conversations' => $this->messaging->inboxForEmployee($employee),
        ]);
    }

    public function openDirect(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $data = $request->validate([
            'peer_type' => ['required', 'string', 'in:employee,company_admin'],
            'peer_id' => ['required', 'integer', 'min:0'],
        ]);

        $conversation = $this->messaging->openOrCreateDirectForEmployee(
            $employee,
            $data['peer_type'],
            (int) $data['peer_id'],
        );

        return response()->json([
            'conversation' => $this->messaging->serializeConversationSummary(
                $conversation->loadMissing('activeParticipants'),
                null,
                ConversationParticipant::TYPE_EMPLOYEE,
                $employee->id,
            ),
        ], 201);
    }

    public function createGroup(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', 'min:1'],
            'include_admin' => ['sometimes', 'boolean'],
        ]);

        $conversation = $this->messaging->createGroupForEmployee(
            $employee,
            $data['title'],
            $data['member_ids'] ?? [],
            (bool) ($data['include_admin'] ?? false),
        );

        return response()->json([
            'conversation' => $this->messaging->serializeConversationSummary(
                $conversation->loadMissing('activeParticipants'),
                now(),
                ConversationParticipant::TYPE_EMPLOYEE,
                $employee->id,
            ),
        ], 201);
    }

    public function updateMembers(Request $request, int $conversation): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $model = $this->messaging->findConversationOrFail($conversation);

        $data = $request->validate([
            'add_member_ids' => ['sometimes', 'array'],
            'add_member_ids.*' => ['integer', 'min:1'],
            'remove_member_ids' => ['sometimes', 'array'],
            'remove_member_ids.*' => ['integer', 'min:1'],
            'include_admin' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $includeAdmin = array_key_exists('include_admin', $data) ? $data['include_admin'] : null;

        $updated = $this->messaging->updateMembersForEmployee(
            $employee,
            $model,
            $data['add_member_ids'] ?? [],
            $data['remove_member_ids'] ?? [],
            $includeAdmin,
        );

        return response()->json([
            'conversation' => $this->messaging->serializeConversationSummary(
                $updated->loadMissing('activeParticipants'),
                null,
                ConversationParticipant::TYPE_EMPLOYEE,
                $employee->id,
            ),
        ]);
    }

    public function messages(Request $request, int $conversation): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $model = $this->messaging->findConversationOrFail($conversation);

        $afterId = $request->query('after_id');
        $beforeId = $request->query('before_id');

        $payload = $this->messaging->listMessagesForEmployee(
            $employee,
            $model,
            is_numeric($afterId) ? (int) $afterId : null,
            is_numeric($beforeId) ? (int) $beforeId : null,
            is_numeric($request->query('limit')) ? (int) $request->query('limit') : 50,
        );

        return response()->json($payload);
    }

    public function sendMessage(Request $request, int $conversation): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $model = $this->messaging->findConversationOrFail($conversation);
        $company = $request->tenantCompany();

        $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $message = $this->messaging->sendMessageAsEmployee(
            $employee,
            $model,
            $request->input('body'),
            $request->file('attachment'),
            $company?->slug ?? 'unknown',
        );

        return response()->json([
            'message' => $this->messaging->serializeMessage(
                $message,
                ConversationParticipant::TYPE_EMPLOYEE,
                $employee->id,
            ),
        ], 201);
    }

    public function downloadAttachment(Request $request, int $message): BinaryFileResponse|JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $model = Message::query()->withTrashed()->find($message);
        if (! $model || ! $model->hasAttachment()) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $this->messaging->assertEmployeeCanAccessAttachment($employee, $model);

        if (! $this->attachments->exists((string) $model->attachment_path)) {
            return response()->json(['message' => 'Attachment file missing.'], 404);
        }

        return response()->file(
            $this->attachments->absolutePath((string) $model->attachment_path),
            [
                'Content-Type' => $model->attachment_mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes((string) ($model->attachment_name ?: 'attachment')).'"',
            ],
        );
    }

    /**
     * Returns a short-lived signed URL the mobile app can open with the system browser/viewer
     * (no Authorization header required on that follow-up request).
     */
    public function attachmentOpenLink(Request $request, int $message): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $model = Message::query()->withTrashed()->find($message);
        if (! $model || ! $model->hasAttachment()) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $this->messaging->assertEmployeeCanAccessAttachment($employee, $model);

        if (! $this->attachments->exists((string) $model->attachment_path)) {
            return response()->json(['message' => 'Attachment file missing.'], 404);
        }

        $company = $request->tenantCompany();
        if (! $company) {
            return response()->json(['message' => 'Tenant not specified.'], 422);
        }

        // Match the host the app actually used (adb reverse / LAN), not only APP_URL.
        URL::forceRootUrl($request->getSchemeAndHttpHost());

        $url = URL::temporarySignedRoute(
            'api.v1.messaging.attachments.open',
            now()->addMinutes(30),
            [
                'message' => $message,
                'company' => $company->slug,
            ],
        );

        return response()->json([
            'url' => $url,
            'name' => $model->attachment_name,
            'mime' => $model->attachment_mime,
            'expires_in' => 1800,
        ]);
    }

    /**
     * Signed, unauthenticated open endpoint used by the device browser / PDF viewer.
     */
    public function openSignedAttachment(Request $request, int $message): BinaryFileResponse|JsonResponse
    {
        $slug = trim((string) $request->query('company', ''));
        if ($slug === '') {
            return response()->json(['message' => 'Tenant not specified.'], 422);
        }

        /** @var Company|null $company */
        $company = Company::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
        if ($company === null) {
            return response()->json(['message' => 'Unknown or inactive tenant.'], 404);
        }

        DB::setDefaultConnection($company->tenant_connection);

        $model = Message::query()->withTrashed()->find($message);
        if (! $model || ! $model->hasAttachment()) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        if (! $this->attachments->exists((string) $model->attachment_path)) {
            return response()->json(['message' => 'Attachment file missing.'], 404);
        }

        $filename = addslashes((string) ($model->attachment_name ?: 'attachment'));

        return response()->file(
            $this->attachments->absolutePath((string) $model->attachment_path),
            [
                'Content-Type' => $model->attachment_mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ],
        );
    }

    public function blocks(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return response()->json([
            'blocks' => $this->messaging->listBlocks($employee),
        ]);
    }

    public function block(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'min:1'],
        ]);

        $this->messaging->blockEmployee($employee, (int) $data['employee_id']);

        return response()->json(['blocked' => true]);
    }

    public function unblock(Request $request, int $employeeId): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $this->messaging->unblockEmployee($employee, $employeeId);

        return response()->json(['blocked' => false]);
    }

    public function policy(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return response()->json(
            $this->messaging->policyStatusForEmployee($employee),
        );
    }

    public function acceptPolicy(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $acceptance = $this->messaging->acceptPolicy($employee);
        $policy = $this->messaging->currentPolicy();

        return response()->json([
            'accepted' => true,
            'policy_version' => (int) $policy->version,
            'accepted_at' => $acceptance->accepted_at?->toIso8601String(),
        ]);
    }

    public function reportMessage(Request $request, int $message): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $model = $this->messaging->findMessageOrFail($message);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $report = $this->messaging->reportMessageAsEmployee(
            $employee,
            $model,
            $data['reason'],
        );

        return response()->json([
            'reported' => true,
            'report_id' => $report->id,
        ], 201);
    }

    public function reportConversation(Request $request, int $conversation): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $model = $this->messaging->findConversationOrFail($conversation);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $report = $this->messaging->reportPeerInConversationAsEmployee(
            $employee,
            $model,
            $data['reason'],
        );

        return response()->json([
            'reported' => true,
            'report_id' => $report->id,
            'message_id' => $report->message_id,
        ], 201);
    }
}

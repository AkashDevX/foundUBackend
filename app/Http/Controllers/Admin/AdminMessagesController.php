<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\OrganizationPortalUser;
use App\Services\ChatAttachmentStorage;
use App\Services\MessagingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminMessagesController extends Controller
{
    public function __construct(
        private readonly MessagingService $messaging,
        private readonly ChatAttachmentStorage $attachments,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $conversations = $this->messaging->inboxForAdmin();

        if ($request->expectsJson() || $request->boolean('ajax')) {
            return response()->json([
                'conversations' => $conversations,
            ]);
        }

        $activeId = $request->integer('c') ?: null;
        $conversationPayload = null;
        $messages = [];

        if ($activeId) {
            try {
                $model = $this->messaging->findConversationOrFail($activeId);
                $payload = $this->messaging->listMessagesForAdmin($model);
                $conversationPayload = $payload['conversation'];
                $messages = $payload['messages'];
            } catch (\Throwable) {
                $activeId = null;
            }
        }

        return view('admin.messages.index', array_merge($ctx, [
            'conversations' => $conversations,
            'directory' => $this->messaging->directoryForAdmin(),
            'activeConversationId' => $activeId,
            'conversationPayload' => $conversationPayload,
            'messages' => $messages,
            'conversationId' => $activeId,
            'openReports' => $this->messaging->listOpenMessageReportsForAdmin(40),
        ]));
    }

    public function show(Request $request, int $conversation): View|JsonResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $model = $this->messaging->findConversationOrFail($conversation);
        $payload = $this->messaging->listMessagesForAdmin($model);

        if ($request->expectsJson() || $request->boolean('ajax')) {
            return response()->json([
                'conversation' => $payload['conversation'],
                'messages' => $payload['messages'],
                'send_url' => route('admin.messages.send', $conversation),
                'show_url' => route('admin.messages.show', $conversation),
            ]);
        }

        return view('admin.messages.index', array_merge($ctx, [
            'conversations' => $this->messaging->inboxForAdmin(),
            'directory' => $this->messaging->directoryForAdmin(),
            'activeConversationId' => $conversation,
            'conversationPayload' => $payload['conversation'],
            'messages' => $payload['messages'],
            'conversationId' => $conversation,
            'openReports' => $this->messaging->listOpenMessageReportsForAdmin(40),
        ]));
    }

    public function resolveReport(Request $request, int $report): RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:resolved,dismissed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->messaging->resolveMessageReport(
            $report,
            $data['status'],
            $data['notes'] ?? null,
        );

        return redirect()
            ->route('admin.messages.index', array_filter(['c' => $request->integer('c') ?: null]))
            ->with('success', $data['status'] === 'resolved' ? 'Report resolved.' : 'Report dismissed.');
    }

    public function storeDirect(Request $request): RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'min:1'],
        ]);

        $conversation = $this->messaging->openOrCreateDirectForAdmin((int) $data['employee_id']);

        return redirect()->route('admin.messages.index', ['c' => $conversation->id]);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'min:1'],
        ]);

        $conversation = $this->messaging->createGroupForAdmin($data['title'], $data['member_ids']);

        return redirect()->route('admin.messages.index', ['c' => $conversation->id])
            ->with('success', 'Group created.');
    }

    public function send(Request $request, int $conversation): RedirectResponse|JsonResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $model = $this->messaging->findConversationOrFail($conversation);

        $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $this->messaging->sendMessageAsAdmin(
            $model,
            $request->input('body'),
            $request->file('attachment'),
            $ctx['company']->slug,
        );

        if ($request->expectsJson() || $request->boolean('ajax') || $request->ajax()) {
            $payload = $this->messaging->listMessagesForAdmin($model->fresh());

            return response()->json([
                'ok' => true,
                'conversation' => $payload['conversation'],
                'messages' => $payload['messages'],
                'send_url' => route('admin.messages.send', $conversation),
            ]);
        }

        return redirect()->route('admin.messages.index', ['c' => $conversation]);
    }

    public function attachment(Request $request, int $message): BinaryFileResponse|RedirectResponse
    {
        $ctx = $this->pageContext($request);
        $this->useTenant($ctx['connection']);

        $model = Message::query()->withTrashed()->find($message);
        if (! $model || ! $model->hasAttachment()) {
            return redirect()->back()->with('error', 'Attachment not found.');
        }

        $this->messaging->assertAdminCanAccessAttachment($model);

        if (! $this->attachments->exists((string) $model->attachment_path)) {
            return redirect()->back()->with('error', 'Attachment file missing.');
        }

        return response()->file(
            $this->attachments->absolutePath((string) $model->attachment_path),
            [
                'Content-Type' => $model->attachment_mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes((string) ($model->attachment_name ?: 'attachment')).'"',
            ],
        );
    }

    private function useTenant(string $connection): void
    {
        \Illuminate\Support\Facades\DB::setDefaultConnection($connection);
    }

    /**
     * @return array{company: \App\Models\Company, connection: string}
     */
    private function pageContext(Request $request): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();

        return [
            'company' => $company,
            'connection' => $company->tenant_connection,
        ];
    }
}

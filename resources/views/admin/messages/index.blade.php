@extends('layouts.admin')

@section('title', ($conversationPayload['title'] ?? null) ? $conversationPayload['title'].' · Messages' : 'Messages')

@section('heading', 'Messages')

@section('subheading')
    {{ $company->name }}
@endsection

@section('content')
    @php
        use App\Support\DisplayTimezone;

        $activeId = $activeConversationId ?? null;
        $tones = ['#1a73e8', '#188038', '#e37400', '#9334e6', '#d93025', '#007b83'];

        $initials = static function (string $title): string {
            $parts = preg_split('/\s+/', trim($title)) ?: [];
            $parts = array_values(array_filter($parts));
            if ($parts === []) {
                return '?';
            }
            if (count($parts) === 1) {
                return strtoupper(substr($parts[0], 0, 2));
            }

            return strtoupper(substr($parts[0], 0, 1).substr($parts[1], 0, 1));
        };

        $relative = static function (?string $iso): string {
            if (! is_string($iso) || $iso === '') {
                return '';
            }
            try {
                $then = \Carbon\Carbon::parse($iso);
                $diff = (int) $then->diffInSeconds(now());
                if ($diff < 60) {
                    return 'now';
                }
                if ($diff < 3600) {
                    return max(1, (int) floor($diff / 60)).'m';
                }
                if ($diff < 86400) {
                    return max(1, (int) floor($diff / 3600)).'h';
                }
                if ($diff < 86400 * 7) {
                    return max(1, (int) floor($diff / 86400)).'d';
                }

                return DisplayTimezone::format($then, 'j M');
            } catch (\Throwable) {
                return '';
            }
        };

        $preview = static function (array $conversation): string {
            $last = $conversation['last_message'] ?? null;
            if (! is_array($last)) {
                return 'No messages yet';
            }
            $sender = (string) ($last['sender_display_name'] ?? '');
            $type = $last['message_type'] ?? 'text';
            if ($type === 'image') {
                return ($sender !== '' ? $sender.': ' : '').'Photo';
            }
            if ($type === 'file') {
                return ($sender !== '' ? $sender.': ' : '').'File';
            }
            if ($type === 'system') {
                return (string) ($last['body'] ?? 'Updated');
            }
            $body = trim((string) ($last['body'] ?? ''));

            return $body !== ''
                ? ($sender !== '' ? $sender.': ' : '').\Illuminate\Support\Str::limit($body, 64)
                : ($sender !== '' ? $sender.' sent a message' : 'New message');
        };

        $formatTime = static function (?string $iso): string {
            if (! is_string($iso) || $iso === '') {
                return '';
            }
            try {
                return DisplayTimezone::format(\Carbon\Carbon::parse($iso), 'g:i A');
            } catch (\Throwable) {
                return '';
            }
        };

        $isGroup = ($conversationPayload['type'] ?? '') === 'group';
        $participantCount = count($conversationPayload['participants'] ?? []);
        $isModerationView = (bool) ($conversationPayload['is_moderation_view'] ?? false);
        $canSendInActive = $activeId
            ? (bool) ($conversationPayload['can_send'] ?? true)
            : false;
        $threadSubtitle = $activeId
            ? ($isModerationView
                ? 'Report review · read only'
                : ($isGroup
                    ? $participantCount.' member'.($participantCount === 1 ? '' : 's')
                    : 'Direct message'))
            : '';

        $indexUrl = route('admin.messages.index');
        $attachmentUrlTemplate = url('/admin/messages/attachments/__ID__');
        $csrf = csrf_token();
        $openReports = $openReports ?? [];
    @endphp

    @if (count($openReports) > 0)
        <section class="mb-4 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]" aria-label="Open message reports">
            <div class="flex items-start gap-3 border-b border-brand-border/80 bg-gradient-to-r from-brand-primary/[0.06] via-white to-white px-4 py-3.5 sm:px-5">
                <span class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 ring-1 ring-red-100" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" />
                        <line x1="4" y1="22" x2="4" y2="15" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-bold tracking-tight text-brand-primary-dark sm:text-[0.95rem]">Safety reports</h2>
                        <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-700 ring-1 ring-red-100">
                            {{ count($openReports) }} open
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-brand-text-secondary">Employee flags from the mobile app — review and take action.</p>
                </div>
            </div>

            <div class="grid gap-3 p-3 sm:p-4 xl:grid-cols-2">
                @foreach ($openReports as $report)
                    @php
                        $reporterName = (string) ($report['reporter_display_name'] ?? 'Reporter');
                        $reportedName = (string) ($report['message']['sender_display_name'] ?? 'Unknown sender');
                        $reasonRaw = trim((string) ($report['reason'] ?? ''));
                        $reasonParts = explode(':', $reasonRaw, 2);
                        $reasonLabel = trim($reasonParts[0] !== '' ? $reasonParts[0] : 'Reported');
                        $reasonExtra = isset($reasonParts[1]) ? trim($reasonParts[1]) : '';
                        $msgType = (string) ($report['message']['message_type'] ?? 'text');
                        $msgBody = trim((string) ($report['message']['body'] ?? ''));
                        $msgPreview = match ($msgType) {
                            'image' => 'Photo attachment',
                            'file' => 'File attachment',
                            default => $msgBody !== '' ? $msgBody : 'No text content',
                        };
                        $when = $relative($report['created_at'] ?? null);
                        $tone = $tones[((int) ($report['id'] ?? 0)) % count($tones)];
                        $reporterInitials = $initials($reporterName);
                        $reportedInitials = $initials($reportedName);
                        $conversationId = $report['message']['conversation_id'] ?? null;
                        $isDeleted = ! empty($report['message']['deleted']);
                        if ($when === 'now') {
                            $whenLabel = 'Reported just now';
                        } elseif ($when !== '' && preg_match('/^\d+[mhd]$/', $when)) {
                            $whenLabel = 'Reported '.$when.' ago';
                        } elseif ($when !== '') {
                            $whenLabel = 'Reported '.$when;
                        } else {
                            $whenLabel = null;
                        }
                    @endphp
                    <article class="flex flex-col gap-3 rounded-2xl border border-brand-border/90 bg-gradient-to-br from-white via-white to-brand-surface/80 p-4 shadow-sm ring-1 ring-black/[0.03] transition hover:border-brand-primary/25 hover:shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span
                                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl text-[11px] font-bold text-white shadow-sm ring-1 ring-white/20"
                                    style="background-color: {{ $tone }}"
                                >{{ $reporterInitials }}</span>
                                <span class="shrink-0 text-slate-400" aria-hidden="true">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" /><path d="m13 6 6 6-6 6" />
                                    </svg>
                                </span>
                                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-teal-700 text-[11px] font-bold text-white shadow-sm ring-1 ring-white/20">
                                    {{ $reportedInitials }}
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm leading-snug text-brand-text">
                                        <span class="font-semibold">{{ $reporterName }}</span>
                                        <span class="font-normal text-brand-text-secondary"> reported </span>
                                        <span class="font-semibold">{{ $reportedName }}</span>
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">
                                        {{ $whenLabel ? $whenLabel.' · ' : '' }}Needs review
                                    </p>
                                </div>
                            </div>
                            <span class="shrink-0 max-w-[10.5rem] truncate rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-bold text-red-700 ring-1 ring-red-100">
                                {{ $reasonLabel }}
                            </span>
                        </div>

                        @if ($reasonExtra !== '')
                            <p class="rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-700">{{ $reasonExtra }}</p>
                        @endif

                        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-inner shadow-slate-900/[0.02]">
                            <div class="mb-1 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                </svg>
                                Flagged message
                                @if ($isDeleted)
                                    <span class="rounded-full bg-red-50 px-1.5 py-0.5 text-[9px] font-bold normal-case tracking-normal text-red-700">Deleted</span>
                                @endif
                            </div>
                            <p class="line-clamp-3 text-sm leading-relaxed text-slate-800">{{ \Illuminate\Support\Str::limit($msgPreview, 160) }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 pt-0.5">
                            @if (! empty($conversationId))
                                <a
                                    href="{{ route('admin.messages.show', $conversationId) }}"
                                    class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl border border-brand-border bg-white px-3 py-1.5 text-xs font-bold text-brand-primary shadow-sm transition hover:border-brand-primary/40 hover:bg-brand-surface"
                                >
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                    </svg>
                                    Open chat
                                </a>
                            @endif
                            <form method="POST" action="{{ route('admin.messages.reports.resolve', $report['id']) }}">
                                @csrf
                                <input type="hidden" name="status" value="resolved" />
                                <input type="hidden" name="c" value="{{ $activeId }}" />
                                <button
                                    type="submit"
                                    class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-md shadow-emerald-600/20 transition hover:bg-emerald-700"
                                >
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 6 9 17l-5-5" />
                                    </svg>
                                    Resolve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.messages.reports.resolve', $report['id']) }}">
                                @csrf
                                <input type="hidden" name="status" value="dismissed" />
                                <input type="hidden" name="c" value="{{ $activeId }}" />
                                <button
                                    type="submit"
                                    class="inline-flex min-h-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-800"
                                >
                                    Dismiss
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <div
        class="admin-chat"
        data-admin-chat
        data-index-url="{{ $indexUrl }}"
        data-attachment-template="{{ $attachmentUrlTemplate }}"
        data-csrf="{{ $csrf }}"
        @if($activeId) data-has-thread="1" @endif
    >
        <aside class="admin-chat__rail">
            <div class="admin-chat__rail-head">
                <h2 class="admin-chat__rail-title">Chat</h2>
                <button type="button" class="admin-chat__icon-btn" data-open-modal="group" title="New group" aria-label="New group">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M19 8v6" />
                        <path d="M22 11h-6" />
                    </svg>
                </button>
                <button type="button" class="admin-chat__icon-btn admin-chat__icon-btn--primary" data-open-modal="direct" title="New chat" aria-label="New chat">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 20h9" />
                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
                    </svg>
                </button>
            </div>

            <div class="admin-chat__search">
                <label class="admin-chat__search-wrap">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                    <input type="search" placeholder="Search chat" data-chat-filter autocomplete="off" />
                </label>
            </div>

            <div class="admin-chat__list" data-chat-list>
                @forelse ($conversations as $conversation)
                    @php
                        $cid = (int) ($conversation['id'] ?? 0);
                        $unread = (int) ($conversation['unread_count'] ?? 0);
                        $isGroupRow = ($conversation['type'] ?? '') === 'group';
                        $tone = $tones[$cid % count($tones)];
                        $time = $relative($conversation['last_message_at'] ?? ($conversation['last_message']['created_at'] ?? null));
                        $title = (string) ($conversation['title'] ?? 'Conversation');
                        $isActive = $activeId !== null && (int) $activeId === $cid;
                    @endphp
                    <button
                        type="button"
                        class="admin-chat__row {{ $isActive ? 'is-active' : '' }} {{ $unread > 0 ? 'is-unread' : '' }}"
                        data-chat-row
                        data-conversation-id="{{ $cid }}"
                        data-conversation-url="{{ route('admin.messages.show', $cid) }}"
                        data-search="{{ strtolower($title.' '.$preview($conversation)) }}"
                    >
                        <span class="admin-chat__avatar" style="background: {{ $tone }}">
                            @if ($isGroupRow)
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            @else
                                {{ $initials($title) }}
                            @endif
                        </span>
                        <div class="admin-chat__row-body">
                            <div class="admin-chat__row-top">
                                <span class="admin-chat__row-title">{{ $title }}</span>
                                @if ($time !== '')
                                    <span class="admin-chat__row-time">{{ $time }}</span>
                                @endif
                            </div>
                            <div class="admin-chat__row-preview">
                                <span class="admin-chat__row-preview-text">{{ $preview($conversation) }}</span>
                                @if ($unread > 0)
                                    <span class="admin-chat__badge">{{ $unread > 99 ? '99+' : $unread }}</span>
                                @endif
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="admin-chat__list-empty">
                        No conversations yet.<br>Start a new chat to message an employee.
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="admin-chat__stage {{ $activeId ? 'is-mobile-open' : '' }}" data-chat-stage>
            <div class="admin-chat__empty" data-chat-empty @if($activeId) hidden @endif>
                <div class="admin-chat__empty-icon">
                    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>
                </div>
                <h3>Select a conversation</h3>
                <p>Pick a chat from the left, or start a new direct message or group with your team.</p>
                <div class="admin-chat__empty-actions">
                    <button type="button" class="admin-chat__btn admin-chat__btn--primary" data-open-modal="direct">New chat</button>
                    <button type="button" class="admin-chat__btn admin-chat__btn--ghost" data-open-modal="group">New group</button>
                </div>
            </div>

            <div class="admin-chat__loading" data-chat-loading hidden>
                <div class="admin-chat__loading-spinner" aria-hidden="true"></div>
                <p>Loading conversation…</p>
            </div>

            <div
                class="admin-chat__thread"
                data-chat-thread
                @if ($activeId)
                    data-conversation-id="{{ $activeId }}"
                    data-conversation-url="{{ route('admin.messages.show', $activeId) }}"
                    data-can-send="{{ $canSendInActive ? '1' : '0' }}"
                @endif
                @unless($activeId) hidden @endunless
            >
                <header class="admin-chat__thread-head">
                    <button type="button" class="admin-chat__icon-btn admin-chat__back" data-chat-back aria-label="Back to list">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    </button>
                    <span class="admin-chat__avatar" data-thread-avatar style="background: {{ $isGroup ? '#188038' : '#1a73e8' }}; width: 2.25rem; height: 2.25rem; font-size: 0.7rem;">
                        @if ($activeId)
                            @if ($isGroup)
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            @else
                                {{ $initials((string) ($conversationPayload['title'] ?? '')) }}
                            @endif
                        @endif
                    </span>
                    <div class="admin-chat__thread-meta">
                        <h2 data-thread-title>{{ $conversationPayload['title'] ?? 'Conversation' }}</h2>
                        <p data-thread-subtitle>{{ $threadSubtitle }}</p>
                    </div>
                </header>

                <div class="admin-chat__messages" id="admin-message-thread" data-thread-messages>
                    @if ($activeId)
                        @forelse ($messages as $message)
                            @php
                                $isAdmin = ($message['sender_type'] ?? '') === 'company_admin';
                                $isSystem = ($message['message_type'] ?? '') === 'system';
                                $timeLabel = $formatTime($message['created_at'] ?? null);
                                $senderInitial = strtoupper(substr(trim((string) ($message['sender_display_name'] ?? '?')), 0, 1));
                            @endphp
                            @if ($isSystem)
                                <div class="admin-chat__system" data-message-id="{{ $message['id'] }}"><span>{{ $message['body'] }}</span></div>
                            @else
                                <div class="admin-chat__msg {{ $isAdmin ? 'admin-chat__msg--out' : 'admin-chat__msg--in' }}" data-message-id="{{ $message['id'] }}">
                                    @unless ($isAdmin)
                                        <span class="admin-chat__msg-avatar">{{ $senderInitial }}</span>
                                    @endunless
                                    <div class="admin-chat__bubble">
                                        @unless ($isAdmin)
                                            <p class="admin-chat__bubble-sender">{{ $message['sender_display_name'] }}</p>
                                        @endunless
                                        @if (! empty($message['body']))
                                            <p class="admin-chat__bubble-text">{{ $message['body'] }}</p>
                                        @endif
                                        @if (! empty($message['attachment']))
                                            <a href="{{ route('admin.messages.attachment', $message['id']) }}" class="admin-chat__bubble-attach" target="_blank" rel="noopener">
                                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $message['attachment']['name'] ?? 'attachment' }}</span>
                                            </a>
                                        @endif
                                        @if ($timeLabel !== '')
                                            <p class="admin-chat__bubble-time">{{ $timeLabel }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="admin-chat__empty" style="background: transparent;">
                                <h3 style="font-size: 1.1rem;">No messages yet</h3>
                                <p>Send the first message to get started.</p>
                            </div>
                        @endforelse
                    @endif
                </div>

                <form
                    method="post"
                    action="{{ $activeId ? route('admin.messages.send', $activeId) : '#' }}"
                    enctype="multipart/form-data"
                    class="admin-chat__composer"
                    data-thread-composer
                    @if ($activeId && ! $canSendInActive) hidden @endif
                >
                    @csrf
                    <div class="admin-chat__composer-bar">
                        <label class="admin-chat__icon-btn" for="admin-chat-attachment" title="Attach file" style="width:2.35rem;height:2.35rem;">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                            <span class="sr-only">Attach</span>
                            <input id="admin-chat-attachment" type="file" name="attachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" class="sr-only" />
                        </label>
                        <textarea id="admin-chat-body" name="body" rows="1" placeholder="Message…"></textarea>
                        <button type="submit" class="admin-chat__send" aria-label="Send message" title="Send" data-send-btn>
                            <svg class="admin-chat__send-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                            <span class="admin-chat__send-spinner" aria-hidden="true"></span>
                        </button>
                    </div>
                    <p id="admin-chat-attachment-name" class="admin-chat__attach-name" hidden></p>
                </form>
                <div
                    class="border-t border-slate-200 bg-amber-50 px-4 py-3 text-center text-xs font-medium text-amber-900"
                    data-moderation-notice
                    @if (! ($activeId && ! $canSendInActive)) hidden @endif
                >
                    Read-only review — this is an employee chat. You can review the thread from the safety report, but you can’t send messages here.
                </div>
            </div>
        </section>

        <div class="admin-chat__modal" data-modal="direct" role="dialog" aria-modal="true" aria-labelledby="modal-direct-title">
            <div class="admin-chat__modal-card">
                <div class="admin-chat__modal-head">
                    <div>
                        <h3 id="modal-direct-title">New chat</h3>
                        <p>Message one employee</p>
                    </div>
                    <button type="button" class="admin-chat__icon-btn" data-close-modal aria-label="Close">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form method="post" action="{{ route('admin.messages.direct.store') }}" class="admin-chat__modal-body">
                    @csrf
                    <label class="admin-chat__field">
                        <span>Employee</span>
                        <select name="employee_id" required>
                            <option value="">Select employee…</option>
                            @foreach ($directory as $person)
                                <option value="{{ $person['id'] }}">{{ $person['display_name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="admin-chat__btn admin-chat__btn--primary" style="width:100%;">Start chat</button>
                </form>
            </div>
        </div>

        <div class="admin-chat__modal" data-modal="group" role="dialog" aria-modal="true" aria-labelledby="modal-group-title">
            <div class="admin-chat__modal-card">
                <div class="admin-chat__modal-head">
                    <div>
                        <h3 id="modal-group-title">New group</h3>
                        <p>Name the group and add members</p>
                    </div>
                    <button type="button" class="admin-chat__icon-btn" data-close-modal aria-label="Close">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form method="post" action="{{ route('admin.messages.groups.store') }}" class="admin-chat__modal-body">
                    @csrf
                    <label class="admin-chat__field">
                        <span>Title</span>
                        <input type="text" name="title" required maxlength="120" placeholder="e.g. Site A morning crew" />
                    </label>
                    <label class="admin-chat__field">
                        <span>Members</span>
                        <select name="member_ids[]" multiple required size="7">
                            @foreach ($directory as $person)
                                <option value="{{ $person['id'] }}">{{ $person['display_name'] }}</option>
                            @endforeach
                        </select>
                        <p class="admin-chat__field-hint">Hold Ctrl / Cmd to select multiple.</p>
                    </label>
                    <button type="submit" class="admin-chat__btn admin-chat__btn--primary" style="width:100%;">Create group</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var root = document.querySelector('[data-admin-chat]');
            if (!root) return;

            var stage = root.querySelector('[data-chat-stage]');
            var emptyEl = root.querySelector('[data-chat-empty]');
            var loadingEl = root.querySelector('[data-chat-loading]');
            var threadEl = root.querySelector('[data-chat-thread]');
            var messagesEl = root.querySelector('[data-thread-messages]');
            var titleEl = root.querySelector('[data-thread-title]');
            var subtitleEl = root.querySelector('[data-thread-subtitle]');
            var avatarEl = root.querySelector('[data-thread-avatar]');
            var composer = root.querySelector('[data-thread-composer]');
            var indexUrl = root.getAttribute('data-index-url') || '/admin/messages';
            var attachTpl = root.getAttribute('data-attachment-template') || '';
            var activeId = null;
            var activeUrl = null;
            var loadToken = 0;
            var lastMessageId = 0;
            var pollTimer = null;
            var sending = false;
            var stickToBottom = true;

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function initials(title) {
                var parts = String(title || '').trim().split(/\s+/).filter(Boolean);
                if (!parts.length) return '?';
                if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }

            function formatTime(iso) {
                if (!iso) return '';
                try {
                    return new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                } catch (e) {
                    return '';
                }
            }

            function attachmentUrl(id) {
                return attachTpl.replace('__ID__', String(id));
            }

            function renderMessages(messages) {
                if (!messages || !messages.length) {
                    return '<div class="admin-chat__empty" style="background:transparent;"><h3 style="font-size:1.1rem;">No messages yet</h3><p>Send the first message to get started.</p></div>';
                }
                return messages.map(function (m) {
                    if (m.message_type === 'system') {
                        return '<div class="admin-chat__system" data-message-id="' + esc(m.id) + '"><span>' + esc(m.body || '') + '</span></div>';
                    }
                    var isAdmin = m.sender_type === 'company_admin';
                    var time = formatTime(m.created_at);
                    var html = '<div class="admin-chat__msg ' + (isAdmin ? 'admin-chat__msg--out' : 'admin-chat__msg--in') + '" data-message-id="' + esc(m.id) + '">';
                    if (!isAdmin) {
                        html += '<span class="admin-chat__msg-avatar">' + esc((m.sender_display_name || '?').trim().charAt(0).toUpperCase()) + '</span>';
                    }
                    html += '<div class="admin-chat__bubble">';
                    if (!isAdmin) {
                        html += '<p class="admin-chat__bubble-sender">' + esc(m.sender_display_name || '') + '</p>';
                    }
                    if (m.body) {
                        html += '<p class="admin-chat__bubble-text">' + esc(m.body) + '</p>';
                    }
                    if (m.attachment) {
                        html += '<a href="' + esc(attachmentUrl(m.id)) + '" class="admin-chat__bubble-attach" target="_blank" rel="noopener">' +
                            '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>' +
                            '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(m.attachment.name || 'attachment') + '</span></a>';
                    }
                    if (time) {
                        html += '<p class="admin-chat__bubble-time">' + esc(time) + '</p>';
                    }
                    html += '</div></div>';
                    return html;
                }).join('');
            }

            function setPanel(mode) {
                if (emptyEl) emptyEl.hidden = mode !== 'empty';
                if (loadingEl) loadingEl.hidden = mode !== 'loading';
                if (threadEl) threadEl.hidden = mode !== 'thread';
                if (stage) {
                    var mobile = window.matchMedia('(max-width: 767px)').matches;
                    if (mobile && (mode === 'thread' || mode === 'loading')) {
                        stage.classList.add('is-mobile-open');
                    } else {
                        stage.classList.remove('is-mobile-open');
                    }
                }
            }

            function setActiveRow(id) {
                activeId = id;
                root.querySelectorAll('[data-chat-row]').forEach(function (row) {
                    var match = String(row.getAttribute('data-conversation-id')) === String(id);
                    row.classList.toggle('is-active', match);
                    if (match) {
                        row.classList.remove('is-unread');
                        var badge = row.querySelector('.admin-chat__badge');
                        if (badge) badge.remove();
                    }
                });
            }

            function nearBottom() {
                if (!messagesEl) return true;
                return (messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight) < 80;
            }

            /** Scroll after layout — must run while the thread panel is visible. */
            function scrollMessagesToBottom() {
                if (!messagesEl) return;
                var run = function () {
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                };
                stickToBottom = true;
                run();
                requestAnimationFrame(function () {
                    run();
                    requestAnimationFrame(run);
                });
            }

            function rememberLastMessageId(messages) {
                lastMessageId = 0;
                (messages || []).forEach(function (m) {
                    var id = Number(m.id || 0);
                    if (id > lastMessageId) lastMessageId = id;
                });
            }

            function updateInboxRow(conversation) {
                if (!conversation || !conversation.id) return;
                var row = root.querySelector('[data-conversation-id="' + conversation.id + '"]');
                if (!row) return;

                var last = conversation.last_message;
                var preview = row.querySelector('.admin-chat__row-preview-text');
                var timeEl = row.querySelector('.admin-chat__row-time');
                if (preview) {
                    if (!last) {
                        preview.textContent = 'No messages yet';
                    } else if (last.message_type === 'image') {
                        preview.textContent = (last.sender_display_name ? last.sender_display_name + ': ' : '') + 'Photo';
                    } else if (last.message_type === 'file') {
                        preview.textContent = (last.sender_display_name ? last.sender_display_name + ': ' : '') + 'File';
                    } else if (last.message_type === 'system') {
                        preview.textContent = last.body || 'Updated';
                    } else {
                        var body = (last.body || '').trim();
                        var who = last.sender_display_name ? last.sender_display_name + ': ' : '';
                        preview.textContent = body ? who + body : who + 'sent a message';
                    }
                }
                if (timeEl && conversation.last_message_at) {
                    try {
                        var then = new Date(conversation.last_message_at).getTime();
                        var diff = Math.max(0, Math.floor((Date.now() - then) / 1000));
                        timeEl.textContent = diff < 60 ? 'now'
                            : diff < 3600 ? Math.floor(diff / 60) + 'm'
                            : diff < 86400 ? Math.floor(diff / 3600) + 'h'
                            : Math.floor(diff / 86400) + 'd';
                    } catch (e) {}
                }

                var unread = Number(conversation.unread_count || 0);
                var isActive = String(conversation.id) === String(activeId);
                if (isActive) {
                    row.classList.remove('is-unread');
                    var activeBadge = row.querySelector('.admin-chat__badge');
                    if (activeBadge) activeBadge.remove();
                    return;
                }

                var badge = row.querySelector('.admin-chat__badge');
                if (unread > 0) {
                    row.classList.add('is-unread');
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'admin-chat__badge';
                        var previewWrap = row.querySelector('.admin-chat__row-preview');
                        if (previewWrap) previewWrap.appendChild(badge);
                    }
                    badge.textContent = unread > 99 ? '99+' : String(unread);
                } else {
                    row.classList.remove('is-unread');
                    if (badge) badge.remove();
                }
            }

            function pollActiveThread() {
                if (!activeId || !activeUrl || sending) return;
                if (document.hidden) return;
                if (threadEl && threadEl.hidden) return;

                var sep = activeUrl.indexOf('?') >= 0 ? '&' : '?';
                fetch(activeUrl + sep + 'ajax=1', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (res) {
                    if (!res.ok) return null;
                    var type = res.headers.get('content-type') || '';
                    if (type.indexOf('application/json') === -1) return null;
                    return res.json();
                }).then(function (data) {
                    if (!data || !data.messages) return;
                    if (String(activeId) !== String((data.conversation || {}).id || activeId)) return;

                    var maxId = 0;
                    data.messages.forEach(function (m) {
                        var id = Number(m.id || 0);
                        if (id > maxId) maxId = id;
                    });
                    if (maxId <= lastMessageId && data.messages.length === (messagesEl ? messagesEl.querySelectorAll('.admin-chat__msg, .admin-chat__system').length : -1)) {
                        updateInboxRow(data.conversation);
                        return;
                    }

                    var keepBottom = stickToBottom || nearBottom();
                    if (messagesEl) {
                        messagesEl.innerHTML = renderMessages(data.messages);
                        if (keepBottom) scrollMessagesToBottom();
                    }
                    rememberLastMessageId(data.messages);
                    updateInboxRow(data.conversation);
                }).catch(function () {});
            }

            function pollInbox() {
                if (document.hidden) return;
                var url = indexUrl.replace(/\?.*$/, '') + '?ajax=1';
                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (res) {
                    if (!res.ok) return null;
                    var type = res.headers.get('content-type') || '';
                    if (type.indexOf('application/json') === -1) return null;
                    return res.json();
                }).then(function (data) {
                    if (!data || !Array.isArray(data.conversations)) return;
                    data.conversations.forEach(updateInboxRow);
                }).catch(function () {});
            }

            function startPolling() {
                if (pollTimer) clearInterval(pollTimer);
                pollTimer = setInterval(function () {
                    pollActiveThread();
                    pollInbox();
                }, 4000);
            }

            function stopPolling() {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }

            function bindComposerUi() {
                var attach = document.getElementById('admin-chat-attachment');
                var attachName = document.getElementById('admin-chat-attachment-name');
                if (attach && attachName) {
                    attach.onchange = function () {
                        var has = attach.files && attach.files[0];
                        attachName.textContent = has ? attach.files[0].name : '';
                        attachName.hidden = !has;
                    };
                }
                var body = document.getElementById('admin-chat-body');
                if (body) {
                    var resize = function () {
                        body.style.height = 'auto';
                        body.style.height = Math.min(body.scrollHeight, 120) + 'px';
                    };
                    body.oninput = resize;
                    resize();
                }
            }

            function setSending(on) {
                sending = !!on;
                var btn = root.querySelector('[data-send-btn]');
                if (!btn) return;
                btn.classList.toggle('is-sending', !!on);
                btn.disabled = !!on;
            }

            function sendMessage(e) {
                if (!composer || composer.hidden) return;
                e.preventDefault();
                var action = composer.getAttribute('action');
                if (!action || action === '#') return;

                var bodyInput = document.getElementById('admin-chat-body');
                var attachInput = document.getElementById('admin-chat-attachment');
                var text = bodyInput ? bodyInput.value.trim() : '';
                var hasFile = attachInput && attachInput.files && attachInput.files[0];
                if (!text && !hasFile) return;

                var formData = new FormData(composer);
                formData.append('ajax', '1');
                setSending(true);

                fetch(action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData,
                    credentials: 'same-origin'
                }).then(function (res) {
                    if (!res.ok) throw new Error('send failed');
                    var type = res.headers.get('content-type') || '';
                    if (type.indexOf('application/json') === -1) throw new Error('not json');
                    return res.json();
                }).then(function (data) {
                    if (bodyInput) {
                        bodyInput.value = '';
                        bodyInput.style.height = 'auto';
                    }
                    if (attachInput) attachInput.value = '';
                    var attachName = document.getElementById('admin-chat-attachment-name');
                    if (attachName) {
                        attachName.textContent = '';
                        attachName.hidden = true;
                    }
                    if (messagesEl) {
                        messagesEl.innerHTML = renderMessages(data.messages || []);
                        scrollMessagesToBottom();
                    }
                    rememberLastMessageId(data.messages || []);
                    // Refresh preview on active row
                    updateInboxRow(data.conversation || {
                        id: activeId,
                        last_message: (data.messages || [])[(data.messages || []).length - 1] || null,
                        unread_count: 0
                    });
                }).catch(function () {
                    alert('Couldn’t send the message. Please try again.');
                }).finally(function () {
                    setSending(false);
                    bindComposerUi();
                });
            }

            if (composer) {
                composer.addEventListener('submit', sendMessage);
            }

            function showThread(payload, url, push) {
                var conv = payload.conversation || {};
                var isGroup = conv.type === 'group';
                var count = (conv.participants || []).length;
                var title = conv.title || 'Conversation';
                var isModeration = !!conv.is_moderation_view || conv.can_send === false;
                var subtitle = isModeration
                    ? 'Report review · read only'
                    : (isGroup
                        ? (count + ' member' + (count === 1 ? '' : 's'))
                        : 'Direct message');

                if (titleEl) titleEl.textContent = title;
                if (subtitleEl) subtitleEl.textContent = subtitle;
                if (avatarEl) {
                    avatarEl.style.background = isGroup ? '#188038' : '#1a73e8';
                    if (isGroup) {
                        avatarEl.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
                    } else {
                        avatarEl.textContent = initials(title);
                    }
                }
                if (composer) {
                    if (payload.send_url) {
                        composer.setAttribute('action', payload.send_url);
                    }
                    composer.hidden = isModeration;
                }
                var moderationNotice = root.querySelector('[data-moderation-notice]');
                if (moderationNotice) {
                    moderationNotice.hidden = !isModeration;
                }
                if (threadEl) {
                    threadEl.setAttribute('data-conversation-id', String(conv.id || ''));
                    threadEl.setAttribute('data-conversation-url', payload.show_url || url || '');
                    threadEl.setAttribute('data-can-send', isModeration ? '0' : '1');
                }
                activeUrl = payload.show_url || url || activeUrl;
                activeId = conv.id || activeId;
                // Show thread first so the messages container has a real height, then scroll.
                setPanel('thread');
                if (messagesEl) {
                    messagesEl.innerHTML = renderMessages(payload.messages || []);
                    scrollMessagesToBottom();
                }
                rememberLastMessageId(payload.messages || []);
                setActiveRow(conv.id);
                document.title = title + ' · Messages';
                if (push) {
                    // Prefer clean ?c= only
                    var next = indexUrl.replace(/\?.*$/, '') + '?c=' + encodeURIComponent(conv.id);
                    history.pushState({ conversationId: conv.id }, '', next);
                }
                bindComposerUi();
                startPolling();
            }

            function showEmpty(push) {
                setPanel('empty');
                setActiveRow(null);
                activeUrl = null;
                lastMessageId = 0;
                document.title = 'Messages';
                if (push) history.pushState({ conversationId: null }, '', indexUrl.replace(/\?.*$/, ''));
                startPolling();
            }

            function loadConversation(url, id, push) {
                var token = ++loadToken;
                activeUrl = url;
                setPanel('loading');
                setActiveRow(id);
                var sep = url.indexOf('?') >= 0 ? '&' : '?';
                fetch(url + sep + 'ajax=1', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (res) {
                    if (!res.ok) throw new Error('Failed to load');
                    var type = res.headers.get('content-type') || '';
                    if (type.indexOf('application/json') === -1) throw new Error('Not json');
                    return res.json();
                }).then(function (data) {
                    if (token !== loadToken) return;
                    showThread(data, url, push);
                }).catch(function () {
                    if (token !== loadToken) return;
                    if (messagesEl) {
                        messagesEl.innerHTML = '<div class="admin-chat__empty" style="background:transparent;"><h3 style="font-size:1.1rem;">Couldn’t load chat</h3><p>Please try again.</p></div>';
                    }
                    if (titleEl) titleEl.textContent = 'Conversation';
                    if (subtitleEl) subtitleEl.textContent = '';
                    if (composer) composer.setAttribute('action', url.replace(/\?.*$/, '') + '/send');
                    setPanel('thread');
                    startPolling();
                });
            }

            root.addEventListener('click', function (e) {
                var row = e.target.closest('[data-chat-row]');
                if (!row || !root.contains(row)) return;
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
                e.preventDefault();
                e.stopPropagation();
                var url = row.getAttribute('data-conversation-url') || row.getAttribute('href');
                var id = row.getAttribute('data-conversation-id');
                if (!url || !id) return;
                if (String(id) === String(activeId) && threadEl && !threadEl.hidden) return;
                loadConversation(url, id, true);
            });

            var backBtn = root.querySelector('[data-chat-back]');
            if (backBtn) {
                backBtn.addEventListener('click', function () {
                    showEmpty(true);
                });
            }

            window.addEventListener('popstate', function (e) {
                var id = e.state && e.state.conversationId;
                if (id) {
                    var row = root.querySelector('[data-conversation-id="' + id + '"]');
                    var url = row ? row.getAttribute('data-conversation-url') : null;
                    if (!url) {
                        url = '{{ url('/admin/messages') }}/' + encodeURIComponent(id);
                    }
                    loadConversation(url, id, false);
                } else {
                    showEmpty(false);
                }
            });

            // Search + modals
            var filter = root.querySelector('[data-chat-filter]');
            if (filter) {
                filter.addEventListener('input', function () {
                    var q = (filter.value || '').toLowerCase().trim();
                    root.querySelectorAll('[data-chat-row]').forEach(function (row) {
                        var hay = row.getAttribute('data-search') || '';
                        row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
                    });
                });
            }
            function openModal(name) {
                root.querySelectorAll('[data-modal]').forEach(function (el) {
                    el.classList.toggle('is-open', el.getAttribute('data-modal') === name);
                });
            }
            function closeModals() {
                root.querySelectorAll('[data-modal]').forEach(function (el) {
                    el.classList.remove('is-open');
                });
            }
            root.querySelectorAll('[data-open-modal]').forEach(function (btn) {
                btn.addEventListener('click', function () { openModal(btn.getAttribute('data-open-modal')); });
            });
            root.querySelectorAll('[data-close-modal]').forEach(function (btn) {
                btn.addEventListener('click', closeModals);
            });
            root.querySelectorAll('[data-modal]').forEach(function (modal) {
                modal.addEventListener('click', function (e) { if (e.target === modal) closeModals(); });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeModals();
            });

            if (messagesEl) {
                messagesEl.addEventListener('scroll', function () {
                    stickToBottom = nearBottom();
                });
            }
            bindComposerUi();

            var initialActive = root.querySelector('[data-chat-row].is-active');
            if (initialActive) {
                activeId = initialActive.getAttribute('data-conversation-id');
                activeUrl = initialActive.getAttribute('data-conversation-url');
                lastMessageId = 0;
                if (messagesEl) {
                    messagesEl.querySelectorAll('[data-message-id]').forEach(function (el) {
                        var id = Number(el.getAttribute('data-message-id') || 0);
                        if (id > lastMessageId) lastMessageId = id;
                    });
                    scrollMessagesToBottom();
                }
                history.replaceState(
                    { conversationId: Number(activeId) },
                    '',
                    indexUrl.replace(/\?.*$/, '') + '?c=' + encodeURIComponent(activeId)
                );
            } else if (threadEl && threadEl.getAttribute('data-conversation-id')) {
                // Report review / deep-link: conversation may not be in the admin inbox list.
                activeId = threadEl.getAttribute('data-conversation-id');
                activeUrl = threadEl.getAttribute('data-conversation-url');
                lastMessageId = 0;
                if (messagesEl) {
                    messagesEl.querySelectorAll('[data-message-id]').forEach(function (el) {
                        var id = Number(el.getAttribute('data-message-id') || 0);
                        if (id > lastMessageId) lastMessageId = id;
                    });
                    scrollMessagesToBottom();
                }
                history.replaceState(
                    { conversationId: Number(activeId) },
                    '',
                    indexUrl.replace(/\?.*$/, '') + '?c=' + encodeURIComponent(activeId)
                );
            } else {
                history.replaceState({ conversationId: null }, '', indexUrl.replace(/\?.*$/, ''));
            }

            startPolling();
            if (activeId && activeUrl) {
                pollActiveThread();
            }
            pollInbox();

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    pollActiveThread();
                    pollInbox();
                }
            });
            window.addEventListener('beforeunload', stopPolling);
        })();
    </script>
@endsection

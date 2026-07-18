@php
    /** @var \App\Models\OrganizationPortalUser $user */
    $confirmText = $confirmText ?? 'You will be signed out of the admin portal.';
    $name = trim((string) ($user->name ?? ''));
    $email = trim((string) ($user->email ?? ''));
    $parts = preg_split('/\s+/', $name) ?: [];
    if (count($parts) >= 2) {
        $initials = strtoupper(substr($parts[0], 0, 1).substr($parts[count($parts) - 1], 0, 1));
    } elseif ($name !== '') {
        $initials = strtoupper(substr($name, 0, 2));
    } elseif ($email !== '') {
        $initials = strtoupper(substr($email, 0, 2));
    } else {
        $initials = 'U';
    }
    $orgLabel = $orgLabel ?? null;
@endphp

<div class="relative" data-account-menu>
    <button
        type="button"
        class="group inline-flex size-10 items-center justify-center rounded-full bg-gradient-to-br from-brand-primary to-brand-primary-dark text-[12px] font-bold text-white shadow-sm ring-2 ring-white transition hover:shadow-md hover:ring-brand-primary/20 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-primary/25"
        data-account-menu-toggle
        aria-expanded="false"
        aria-haspopup="menu"
        aria-label="Account menu"
        title="{{ $name !== '' ? $name : 'Account' }}"
    >
        {{ $initials }}
    </button>

    <div
        class="absolute right-0 z-50 mt-3 hidden w-[17.5rem] origin-top-right overflow-hidden rounded-2xl border border-brand-border/80 bg-white shadow-2xl shadow-brand-primary-dark/10 ring-1 ring-black/[0.03]"
        data-account-menu-panel
        role="menu"
    >
        <div class="bg-gradient-to-br from-brand-surface via-white to-white px-4 pb-4 pt-5">
            <div class="flex items-center gap-3">
                <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-primary to-brand-primary-dark text-sm font-bold text-white shadow-sm">
                    {{ $initials }}
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-brand-text">{{ $name !== '' ? $name : 'Signed in' }}</p>
                    @if ($email !== '')
                        <p class="mt-0.5 truncate text-xs text-brand-text-secondary">{{ $email }}</p>
                    @endif
                </div>
            </div>
            @if ($orgLabel)
                <p class="mt-3 inline-flex max-w-full items-center rounded-full bg-brand-primary/[0.08] px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-brand-primary">
                    <span class="truncate">{{ $orgLabel }}</span>
                </p>
            @endif
        </div>

        <div class="border-t border-brand-border/80 p-2">
            <form
                method="post"
                action="{{ route('portal.logout') }}"
                data-skip-form-busy
                data-confirm="{{ $confirmText }}"
                data-confirm-title="Sign out?"
                data-confirm-confirm="Sign out"
                data-confirm-cancel="Stay signed in"
                data-confirm-icon="question"
            >
                @csrf
                <button
                    type="submit"
                    role="menuitem"
                    class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-brand-text transition hover:bg-brand-surface"
                >
                    <span class="inline-flex items-center gap-2.5">
                        <span class="inline-flex size-8 items-center justify-center rounded-lg bg-red-50 text-red-600">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H9" />
                            </svg>
                        </span>
                        Sign out
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>

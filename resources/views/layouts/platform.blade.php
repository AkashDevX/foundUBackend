<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#003d7a">
    @if (session('success'))
        <meta name="flash-success" content="{{ e(session('success')) }}">
    @endif
    @if (session('error'))
        <meta name="flash-error" content="{{ e(session('error')) }}">
    @endif
    <title>@yield('title', 'Platform') — {{ config('app.name', 'CruLynk') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-surface font-sans text-brand-text antialiased">
    <div class="flex min-h-screen">
        <aside
            id="platform-sidebar"
            class="fixed inset-y-0 left-0 z-40 w-[17rem] -translate-x-full border-r border-white/10 bg-gradient-to-b from-brand-primary-dark via-brand-primary-dark to-[#001428] text-brand-white shadow-xl shadow-black/20 transition-transform duration-300 ease-out lg:static lg:translate-x-0 lg:shadow-none"
            aria-label="Platform navigation"
        >
            <div class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-brand-primary-light/25 to-transparent" aria-hidden="true"></div>
            <div class="relative flex h-full flex-col">
                <div class="flex items-center gap-3 border-b border-white/10 px-5 py-6">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-white shadow-lg shadow-black/25 ring-2 ring-white/25">
                        <svg class="size-6 text-brand-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold tracking-tight text-white">{{ config('app.name', 'CruLynk') }}</p>
                        <p class="text-xs text-white/65">Platform controller</p>
                    </div>
                </div>

                <nav class="flex-1 space-y-1 px-3 py-5 text-sm font-medium">
                    @php
                        $navActive = 'group relative flex items-center gap-3 rounded-xl bg-white/[0.12] px-3 py-3 text-brand-white shadow-inner shadow-black/10 ring-1 ring-white/15';
                        $navInactive = 'group flex items-center gap-3 rounded-xl px-3 py-3 text-white/70 transition hover:bg-white/[0.07] hover:text-white';
                    @endphp
                    <a href="{{ route('platform.dashboard') }}" class="{{ request()->routeIs('platform.dashboard', 'platform.organizations.*') ? $navActive : $navInactive }}" @if(request()->routeIs('platform.dashboard', 'platform.organizations.*')) aria-current="page" @endif>
                        @if(request()->routeIs('platform.dashboard', 'platform.organizations.*'))
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-primary-light" aria-hidden="true"></span>
                        @endif
                        <span class="{{ request()->routeIs('platform.dashboard', 'platform.organizations.*') ? 'ml-1' : '' }} flex size-9 items-center justify-center rounded-lg bg-white/10 text-white">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 12h18"/></svg>
                        </span>
                        <span>Organizations</span>
                    </a>
                    <a href="{{ route('platform.organization-requests.index') }}" class="{{ request()->routeIs('platform.organization-requests.*') ? $navActive : $navInactive }}" @if(request()->routeIs('platform.organization-requests.*')) aria-current="page" @endif>
                        @if(request()->routeIs('platform.organization-requests.*'))
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-primary-light" aria-hidden="true"></span>
                        @endif
                        <span class="{{ request()->routeIs('platform.organization-requests.*') ? 'ml-1' : '' }} flex size-9 items-center justify-center rounded-lg bg-white/10 text-white">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </span>
                        <span>Access requests</span>
                    </a>
                </nav>

                <div class="border-t border-white/10 p-4">
                    @auth('portal')
                        @php
                            /** @var \App\Models\OrganizationPortalUser $pu */
                            $pu = auth('portal')->user();
                        @endphp
                        <div class="rounded-xl bg-black/25 px-4 py-4 ring-1 ring-white/10 backdrop-blur-sm">
                            <p class="truncate text-[11px] font-semibold uppercase tracking-wider text-white/55">Platform admin</p>
                            <p class="mt-2 truncate text-sm font-semibold text-white">{{ $pu->name }}</p>
                            <p class="truncate text-xs text-white/60">{{ $pu->email }}</p>
                            <form
                                method="post"
                                action="{{ route('portal.logout') }}"
                                class="mt-4"
                                data-skip-form-busy
                                data-confirm="You will be signed out of the platform console."
                                data-confirm-title="Sign out?"
                                data-confirm-confirm="Sign out"
                                data-confirm-cancel="Stay signed in"
                                data-confirm-icon="question"
                            >
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-white/12 px-3 py-2.5 text-xs font-semibold text-white ring-1 ring-white/15 transition hover:bg-white/20">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    @endauth
                </div>
            </div>
        </aside>

        <div
            id="platform-sidebar-overlay"
            class="fixed inset-0 z-30 bg-brand-primary-dark/50 opacity-0 backdrop-blur-[2px] transition-opacity duration-200 pointer-events-none lg:hidden"
            aria-hidden="true"
        ></div>

        <div class="flex min-w-0 flex-1 flex-col lg:pl-0">
            <header class="sticky top-0 z-20 border-b border-brand-border bg-white/90 shadow-sm shadow-black/[0.03] backdrop-blur-md supports-[backdrop-filter]:bg-white/75">
                <div class="flex items-center gap-3 px-4 py-3.5 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        id="platform-menu-toggle"
                        class="inline-flex items-center justify-center rounded-xl border border-brand-border bg-white p-2.5 text-brand-primary shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface lg:hidden"
                        aria-controls="platform-sidebar"
                        aria-expanded="false"
                    >
                        <span class="sr-only">Open menu</span>
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-lg font-bold tracking-tight text-brand-text sm:text-xl">@yield('heading', 'Organizations')</h1>
                        <p class="mt-0.5 hidden text-sm leading-snug text-brand-text-secondary sm:block">@yield('subheading', 'Manage tenant organizations')</p>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
                @yield('content')
                @include('partials.cru-lynk-flash')
            </main>
        </div>
    </div>
    <script>
        (function () {
            var toggle = document.getElementById('platform-menu-toggle');
            var sidebar = document.getElementById('platform-sidebar');
            var overlay = document.getElementById('platform-sidebar-overlay');
            if (!toggle || !sidebar || !overlay) return;
            function openMenu() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('opacity-0', 'pointer-events-none');
                toggle.setAttribute('aria-expanded', 'true');
            }
            function closeMenu() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0', 'pointer-events-none');
                toggle.setAttribute('aria-expanded', 'false');
            }
            toggle.addEventListener('click', function () {
                if (sidebar.classList.contains('-translate-x-full')) openMenu();
                else closeMenu();
            });
            overlay.addEventListener('click', closeMenu);
        })();
    </script>
    @stack('scripts')
</body>
</html>

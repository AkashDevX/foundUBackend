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
    <title>@yield('title', 'Platform') - {{ config('app.name', 'CruLynk') }}</title>
    <link rel="icon" href="{{ asset('images/crulynk-logo.png') }}?v=9" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/crulynk-logo.png') }}?v=9">
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
                <div class="border-b border-white/10 px-5 py-5">
                    <img
                        src="{{ asset('images/crulynk-logo.png') }}?v=9"
                        alt="{{ config('app.name', 'CruLynk') }}"
                        width="80"
                        height="68"
                        style="height: 80px; width: auto;"
                        class="mx-auto object-contain drop-shadow-md"
                    >
                    <p class="mt-3 text-center text-xs text-white/65">Platform controller</p>
                </div>

                <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-5 text-sm font-medium">
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
                        @hasSection('subheading')
                            <p class="mt-0.5 hidden text-sm leading-snug text-brand-text-secondary sm:block">@yield('subheading')</p>
                        @endif
                    </div>
                    @auth('portal')
                        @include('admin.partials.account-menu', [
                            'user' => auth('portal')->user(),
                            'orgLabel' => 'Platform admin',
                            'confirmText' => 'You will be signed out of the platform console.',
                        ])
                    @endauth
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

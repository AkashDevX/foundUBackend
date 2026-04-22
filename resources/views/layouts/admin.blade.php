<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#003d7a">
    <title>@yield('title', 'Admin') — {{ config('app.name', 'Attendance') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-surface font-sans text-brand-text antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside
            id="admin-sidebar"
            class="fixed inset-y-0 left-0 z-40 w-[17rem] -translate-x-full border-r border-white/10 bg-gradient-to-b from-brand-primary-dark via-brand-primary-dark to-[#001428] text-brand-white shadow-xl shadow-black/20 transition-transform duration-300 ease-out lg:static lg:translate-x-0 lg:shadow-none"
            aria-label="Main navigation"
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
                        <p class="text-sm font-bold tracking-tight text-white">Workforce</p>
                        <p class="text-xs text-white/65">Organization portal</p>
                    </div>
                </div>
                <nav class="flex-1 space-y-1 px-3 py-5 text-sm font-medium">
                    @php
                        $navActive = 'group relative flex items-center gap-3 rounded-xl bg-white/[0.12] px-3 py-3 text-brand-white shadow-inner shadow-black/10 ring-1 ring-white/15';
                        $navInactive = 'group flex items-center gap-3 rounded-xl px-3 py-3 text-white/70 transition hover:bg-white/[0.07] hover:text-white';
                    @endphp
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? $navActive : $navInactive }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.dashboard'))
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-primary-light" aria-hidden="true"></span>
                        @endif
                        <span class="{{ request()->routeIs('admin.dashboard') ? 'ml-1' : '' }} flex size-9 items-center justify-center rounded-lg bg-white/10 text-white">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                        </span>
                        <span>Registrations</span>
                    </a>
                    <a href="{{ route('admin.workforce') }}" class="{{ request()->routeIs('admin.workforce') ? $navActive : $navInactive }}" @if(request()->routeIs('admin.workforce')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.workforce'))
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-primary-light" aria-hidden="true"></span>
                        @endif
                        <span class="{{ request()->routeIs('admin.workforce') ? 'ml-1' : '' }} flex size-9 items-center justify-center rounded-lg bg-white/10 text-white">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 01-4.681-3.72 8.986 8.986 0 0115.863 0 3 3 0 01-4.681 3.72z" /></svg>
                        </span>
                        <span>Workforce setup</span>
                    </a>
                </nav>
                <div class="border-t border-white/10 p-4">
                    @auth('portal')
                        @php
                            /** @var \App\Models\OrganizationPortalUser $pu */
                            $pu = auth('portal')->user();
                            $pc = $pu->company;
                        @endphp
                        <div class="rounded-xl bg-black/25 px-4 py-4 ring-1 ring-white/10 backdrop-blur-sm">
                            <p class="truncate text-[11px] font-semibold uppercase tracking-wider text-white/55">{{ $pc?->name ?? 'Organization' }}</p>
                            <p class="mt-2 truncate text-sm font-semibold text-white">{{ $pu->name }}</p>
                            <p class="truncate text-xs text-white/60">{{ $pu->email }}</p>
                            <form method="post" action="{{ route('portal.logout') }}" class="mt-4">
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
            id="admin-sidebar-overlay"
            class="fixed inset-0 z-30 bg-brand-primary-dark/50 opacity-0 backdrop-blur-[2px] transition-opacity duration-200 pointer-events-none lg:hidden"
            aria-hidden="true"
        ></div>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col lg:pl-0">
            <header class="sticky top-0 z-20 border-b border-brand-border bg-white/90 shadow-sm shadow-black/[0.03] backdrop-blur-md supports-[backdrop-filter]:bg-white/75">
                <div class="flex items-center gap-3 px-4 py-3.5 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        id="admin-menu-toggle"
                        class="inline-flex items-center justify-center rounded-xl border border-brand-border bg-white p-2.5 text-brand-primary shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface lg:hidden"
                        aria-controls="admin-sidebar"
                        aria-expanded="false"
                    >
                        <span class="sr-only">Open menu</span>
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <div class="min-w-0 flex-1 border-l border-transparent pl-0 lg:border-l-0 lg:pl-0">
                        <h1 class="truncate text-lg font-bold tracking-tight text-brand-text sm:text-xl">@yield('heading', 'Dashboard')</h1>
                        <p class="mt-0.5 hidden text-sm leading-snug text-brand-text-secondary sm:block">@yield('subheading', 'Overview')</p>
                    </div>
                    <div class="hidden items-center gap-3 sm:flex">
                        <label class="relative block">
                            <span class="sr-only">Search</span>
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-brand-icon">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </span>
                            <input
                                type="search"
                                placeholder="Search applicants…"
                                class="w-44 rounded-xl border border-brand-border bg-brand-surface py-2.5 pl-10 pr-3 text-sm text-brand-text placeholder:text-brand-text-secondary transition focus:border-brand-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-primary/20 lg:w-52"
                            />
                        </label>
                        <button type="button" class="relative rounded-xl border border-brand-border bg-white p-2.5 text-brand-icon shadow-sm transition hover:border-brand-primary/40 hover:text-brand-primary" aria-label="Notifications">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            <span class="absolute right-2 top-2 size-2 rounded-full bg-brand-primary-light ring-2 ring-white"></span>
                        </button>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
                @if (session('success'))
                    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-950 shadow-sm" role="status">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-950 shadow-sm" role="alert">
                        {{ session('error') }}
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    <script>
        (function () {
            var toggle = document.getElementById('admin-menu-toggle');
            var sidebar = document.getElementById('admin-sidebar');
            var overlay = document.getElementById('admin-sidebar-overlay');
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
</body>
</html>

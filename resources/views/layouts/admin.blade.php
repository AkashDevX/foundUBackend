<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — {{ config('app.name', 'Attendance') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-background font-sans text-brand-text antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside
            id="admin-sidebar"
            class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full border-r border-white/10 bg-brand-primary-dark text-brand-white transition-transform duration-200 lg:static lg:translate-x-0"
            aria-label="Main navigation"
        >
            <div class="flex h-full flex-col">
                <div class="flex items-center gap-3 border-b border-white/10 px-5 py-6">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-brand-primary-light/30 ring-1 ring-white/20">
                        <svg class="size-5 text-brand-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold tracking-tight">Attendance</p>
                        <p class="text-xs text-white/70">Admin panel</p>
                    </div>
                </div>
                <nav class="flex-1 space-y-1 px-3 py-4 text-sm font-medium">
                    <a href="{{ url('/admin') }}" class="flex items-center gap-3 rounded-lg bg-white/10 px-3 py-2.5 text-brand-white">
                        <svg class="size-5 shrink-0 text-white/90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                        Dashboard
                    </a>
                    <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-white/80 transition hover:bg-white/10 hover:text-brand-white">
                        <svg class="size-5 shrink-0 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                        Daily attendance
                    </a>
                    <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-white/80 transition hover:bg-white/10 hover:text-brand-white">
                        <svg class="size-5 shrink-0 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        People &amp; teams
                    </a>
                    <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-white/80 transition hover:bg-white/10 hover:text-brand-white">
                        <svg class="size-5 shrink-0 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        Reports
                    </a>
                    <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-white/80 transition hover:bg-white/10 hover:text-brand-white">
                        <svg class="size-5 shrink-0 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Settings
                    </a>
                </nav>
                <div class="border-t border-white/10 p-4">
                    <div class="flex items-center gap-3 rounded-lg bg-black/20 px-3 py-2">
                        <div class="flex size-9 items-center justify-center rounded-full bg-brand-primary text-xs font-semibold">AD</div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">Admin user</p>
                            <a href="#" class="text-xs text-brand-link underline-offset-2 hover:underline">View profile</a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <div
            id="admin-sidebar-overlay"
            class="fixed inset-0 z-30 bg-brand-primary-dark/40 opacity-0 pointer-events-none transition-opacity duration-200 lg:hidden"
            aria-hidden="true"
        ></div>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col lg:pl-0">
            <header class="sticky top-0 z-20 border-b border-brand-border bg-brand-card/95 backdrop-blur supports-[backdrop-filter]:bg-brand-card/80">
                <div class="flex items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        id="admin-menu-toggle"
                        class="inline-flex items-center justify-center rounded-lg border border-brand-border bg-brand-card p-2 text-brand-label lg:hidden"
                        aria-controls="admin-sidebar"
                        aria-expanded="false"
                    >
                        <span class="sr-only">Open menu</span>
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-lg font-semibold text-brand-text sm:text-xl">@yield('heading', 'Dashboard')</h1>
                        <p class="hidden text-sm text-brand-text-secondary sm:block">@yield('subheading', "Overview of today's attendance")</p>
                    </div>
                    <div class="hidden items-center gap-2 sm:flex">
                        <label class="relative block">
                            <span class="sr-only">Search</span>
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-brand-icon">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </span>
                            <input
                                type="search"
                                placeholder="Search people…"
                                class="w-48 rounded-lg border border-brand-border bg-brand-input py-2 pl-9 pr-3 text-sm text-brand-text placeholder:text-brand-text-secondary focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25 lg:w-56"
                            />
                        </label>
                        <button type="button" class="relative rounded-lg border border-brand-border p-2 text-brand-icon transition hover:border-brand-primary/40 hover:text-brand-primary" aria-label="Notifications">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            <span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-brand-primary-light ring-2 ring-brand-card"></span>
                        </button>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
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

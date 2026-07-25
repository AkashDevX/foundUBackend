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
    <title>@yield('title', 'Admin') - {{ config('app.name', 'CruLynk') }}</title>
    <link rel="icon" href="{{ asset('images/crulynk-logo.png') }}?v=9" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/crulynk-logo.png') }}?v=9">
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
                <div class="border-b border-white/10 px-5 py-5">
                    <img
                        src="{{ asset('images/crulynk-logo.png') }}?v=9"
                        alt="{{ config('app.name', 'CruLynk') }}"
                        width="80"
                        height="68"
                        style="height: 80px; width: auto;"
                        class="mx-auto object-contain drop-shadow-md"
                    >
                    <p class="mt-3 text-center text-xs text-white/65">Organization portal</p>
                </div>
                <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-5 text-sm font-medium">
                    @php
                        $navActive = 'group relative flex items-center gap-3 rounded-xl bg-white/[0.12] px-3 py-3 text-brand-white shadow-inner shadow-black/10 ring-1 ring-white/15';
                        $navInactive = 'group flex items-center gap-3 rounded-xl px-3 py-3 text-white/70 transition hover:bg-white/[0.07] hover:text-white';
                        $orgSetupActive = request()->routeIs('admin.workforce*') || request()->routeIs('admin.payroll.rates*') || request()->routeIs('admin.payroll.holidays*');
                        $payrollActive = request()->routeIs('admin.employees.time-clock*') || request()->routeIs('admin.payroll.runs*');
                    @endphp
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? $navActive : $navInactive }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.dashboard'))
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-primary-light" aria-hidden="true"></span>
                        @endif
                        <span class="{{ request()->routeIs('admin.dashboard') ? 'ml-1' : '' }} flex size-9 items-center justify-center rounded-lg bg-white/10 text-white">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                        </span>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('admin.registrations.index') }}" class="{{ request()->routeIs('admin.registrations.index') ? $navActive : $navInactive }}" @if(request()->routeIs('admin.registrations.index')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.registrations.index'))
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-primary-light" aria-hidden="true"></span>
                        @endif
                        <span class="{{ request()->routeIs('admin.registrations.index') ? 'ml-1' : '' }} flex size-9 items-center justify-center rounded-lg bg-white/10 text-white">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                        </span>
                        <span>Registrations</span>
                    </a>
                    <button type="button" id="workforce-nav-toggle" class="{{ $orgSetupActive ? $navActive : $navInactive }} w-full" @if($orgSetupActive) aria-current="page" @endif aria-expanded="{{ $orgSetupActive ? 'true' : 'false' }}">
                        @if($orgSetupActive)
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-primary-light" aria-hidden="true"></span>
                        @endif
                        <span class="{{ $orgSetupActive ? 'ml-1' : '' }} flex size-9 items-center justify-center rounded-lg bg-white/10 text-white">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5V4H2v16h5m10 0v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4m10 0H7m10-11h.01M7 9h5" /></svg>
                        </span>
                        <span class="flex-1 text-left">Organization setup</span>
                        <svg class="size-4 transition-transform" id="workforce-nav-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="workforce-nav-submenu" class="ml-12 space-y-1 pb-1 {{ $orgSetupActive ? '' : 'hidden' }}">
                        <a href="{{ route('admin.workforce.departments') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.workforce.departments') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Departments</a>
                        <a href="{{ route('admin.workforce.job-titles') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.workforce.job-titles') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Job titles</a>
                        <a href="{{ route('admin.workforce.work-locations') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.workforce.work-locations') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Work locations</a>
                        <a href="{{ route('admin.workforce.shifts') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.workforce.shifts') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Shifts</a>
                        <a href="{{ route('admin.workforce.leave-types') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.workforce.leave-types') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Leave types</a>
                        <a href="{{ route('admin.payroll.rates') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.payroll.rates*') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Award rates</a>
                        <a href="{{ route('admin.payroll.holidays') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.payroll.holidays*') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Public holidays</a>
                    </div>

                    <button type="button" id="employees-nav-toggle" class="{{ request()->routeIs('admin.employees*') && ! request()->routeIs('admin.employees.time-clock*') ? $navActive : $navInactive }} w-full" @if(request()->routeIs('admin.employees*') && ! request()->routeIs('admin.employees.time-clock*')) aria-current="page" @endif aria-expanded="{{ request()->routeIs('admin.employees*') && ! request()->routeIs('admin.employees.time-clock*') ? 'true' : 'false' }}">
                        @if(request()->routeIs('admin.employees*') && ! request()->routeIs('admin.employees.time-clock*'))
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-primary-light" aria-hidden="true"></span>
                        @endif
                        <span class="{{ request()->routeIs('admin.employees*') && ! request()->routeIs('admin.employees.time-clock*') ? 'ml-1' : '' }} flex size-9 items-center justify-center rounded-lg bg-white/10 text-white">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 01-4.681-3.72 8.986 8.986 0 0115.863 0 3 3 0 01-4.681 3.72z" /></svg>
                        </span>
                        <span class="flex-1 text-left">Employees</span>
                        <svg class="size-4 transition-transform" id="employees-nav-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="employees-nav-submenu" class="ml-12 space-y-1 pb-1 {{ request()->routeIs('admin.employees*') && ! request()->routeIs('admin.employees.time-clock*') ? '' : 'hidden' }}">
                        <a href="{{ route('admin.employees.assignments') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.employees.assignments') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Work assignments</a>
                        <a href="{{ route('admin.employees.profiles') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.employees.profiles') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Employee profiles</a>
                        <a href="{{ route('admin.employees.weekly-schedule') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.employees.weekly-schedule') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Weekly schedule</a>
                        <a href="{{ route('admin.employees.tasks') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.employees.tasks*') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Tasks</a>
                    </div>

                    <button type="button" id="payroll-time-nav-toggle" class="{{ $payrollActive ? $navActive : $navInactive }} w-full" @if($payrollActive) aria-current="page" @endif aria-expanded="{{ $payrollActive ? 'true' : 'false' }}">
                        @if($payrollActive)
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-primary-light" aria-hidden="true"></span>
                        @endif
                        <span class="{{ $payrollActive ? 'ml-1' : '' }} flex size-9 items-center justify-center rounded-lg bg-white/10 text-white">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                        <span class="flex-1 text-left">Payroll</span>
                        <svg class="size-4 transition-transform" id="payroll-time-nav-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="payroll-time-nav-submenu" class="ml-12 space-y-1 pb-1 {{ $payrollActive ? '' : 'hidden' }}">
                        <a href="{{ route('admin.employees.time-clock') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.employees.time-clock*') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Time clock records</a>
                        <a href="{{ route('admin.payroll.runs') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.payroll.runs*') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Payrun</a>
                    </div>

                    <button type="button" id="reports-nav-toggle" class="{{ request()->routeIs('admin.reports*') ? $navActive : $navInactive }} w-full" @if(request()->routeIs('admin.reports*')) aria-current="page" @endif aria-expanded="{{ request()->routeIs('admin.reports*') ? 'true' : 'false' }}">
                        @if(request()->routeIs('admin.reports*'))
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-primary-light" aria-hidden="true"></span>
                        @endif
                        <span class="{{ request()->routeIs('admin.reports*') ? 'ml-1' : '' }} flex size-9 items-center justify-center rounded-lg bg-white/10 text-white">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                        </span>
                        <span class="flex-1 text-left">Reports</span>
                        <svg class="size-4 transition-transform" id="reports-nav-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div id="reports-nav-submenu" class="ml-12 space-y-1 pb-1 {{ request()->routeIs('admin.reports*') ? '' : 'hidden' }}">
                        <a href="{{ route('admin.reports.payroll') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.reports.payroll') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Payroll summary</a>
                        <a href="{{ route('admin.reports.paysheet') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.reports.paysheet') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Paysheet</a>
                        <a href="{{ route('admin.reports.timesheet') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.reports.timesheet') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Timesheet &amp; hours</a>
                        <a href="{{ route('admin.reports.leave') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.reports.leave') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Leave report</a>
                        <a href="{{ route('admin.reports.headcount') }}" class="block rounded-lg px-3 py-2 text-xs {{ request()->routeIs('admin.reports.headcount') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/[0.07] hover:text-white/90' }}">Workforce headcount</a>
                    </div>
                </nav>
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
                        @hasSection('subheading')
                            <p class="mt-0.5 hidden text-sm leading-snug text-brand-text-secondary sm:block">@yield('subheading')</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3">
                        @auth('portal')
                            @include('admin.partials.applicant-search-shell')
                        @endauth
                        @auth('portal')
                            @include('admin.partials.account-menu', [
                                'user' => auth('portal')->user(),
                                'orgLabel' => auth('portal')->user()->company?->name,
                                'confirmText' => 'You will be signed out of the admin portal.',
                            ])
                        @endauth
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
            var toggle = document.getElementById('admin-menu-toggle');
            var sidebar = document.getElementById('admin-sidebar');
            var overlay = document.getElementById('admin-sidebar-overlay');
            var workforceToggle = document.getElementById('workforce-nav-toggle');
            var workforceMenu = document.getElementById('workforce-nav-submenu');
            var workforceChevron = document.getElementById('workforce-nav-chevron');
            var employeesToggle = document.getElementById('employees-nav-toggle');
            var employeesMenu = document.getElementById('employees-nav-submenu');
            var employeesChevron = document.getElementById('employees-nav-chevron');
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

            if (workforceToggle && workforceMenu && workforceChevron) {
                function syncWorkforceChevron() {
                    var expanded = workforceToggle.getAttribute('aria-expanded') === 'true';
                    workforceChevron.style.transform = expanded ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                workforceToggle.addEventListener('click', function () {
                    var expanded = workforceToggle.getAttribute('aria-expanded') === 'true';
                    workforceToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    workforceMenu.classList.toggle('hidden', expanded);
                    syncWorkforceChevron();
                });
                syncWorkforceChevron();
            }

            if (employeesToggle && employeesMenu && employeesChevron) {
                function syncEmployeesChevron() {
                    var expanded = employeesToggle.getAttribute('aria-expanded') === 'true';
                    employeesChevron.style.transform = expanded ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                employeesToggle.addEventListener('click', function () {
                    var expanded = employeesToggle.getAttribute('aria-expanded') === 'true';
                    employeesToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    employeesMenu.classList.toggle('hidden', expanded);
                    syncEmployeesChevron();
                });
                syncEmployeesChevron();
            }

            var payrollTimeToggle = document.getElementById('payroll-time-nav-toggle');
            var payrollTimeMenu = document.getElementById('payroll-time-nav-submenu');
            var payrollTimeChevron = document.getElementById('payroll-time-nav-chevron');
            if (payrollTimeToggle && payrollTimeMenu && payrollTimeChevron) {
                function syncPayrollTimeChevron() {
                    var expanded = payrollTimeToggle.getAttribute('aria-expanded') === 'true';
                    payrollTimeChevron.style.transform = expanded ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                payrollTimeToggle.addEventListener('click', function () {
                    var expanded = payrollTimeToggle.getAttribute('aria-expanded') === 'true';
                    payrollTimeToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    payrollTimeMenu.classList.toggle('hidden', expanded);
                    syncPayrollTimeChevron();
                });
                syncPayrollTimeChevron();
            }

            var reportsToggle = document.getElementById('reports-nav-toggle');
            var reportsMenu = document.getElementById('reports-nav-submenu');
            var reportsChevron = document.getElementById('reports-nav-chevron');
            if (reportsToggle && reportsMenu && reportsChevron) {
                function syncReportsChevron() {
                    var expanded = reportsToggle.getAttribute('aria-expanded') === 'true';
                    reportsChevron.style.transform = expanded ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                reportsToggle.addEventListener('click', function () {
                    var expanded = reportsToggle.getAttribute('aria-expanded') === 'true';
                    reportsToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    reportsMenu.classList.toggle('hidden', expanded);
                    syncReportsChevron();
                });
                syncReportsChevron();
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>

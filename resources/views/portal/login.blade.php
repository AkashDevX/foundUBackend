{{--
  Organization portal login — split layout inspired by modern auth UIs (ELBOD2i-style reference).
  Colors from app mobile / web brand: --color-brand-primary (#003d7a), primary-dark (#002855),
  primary-light (#0052a2); body text/links align with admin theme.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#003d7a">
    @if (session('success'))
        <meta name="flash-success" content="{{ e(session('success')) }}">
    @endif
    @if (isset($errors) && $errors->any())
        <meta name="portal-has-validation-errors" content="1">
    @endif
    <title>Login — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /*
          Hero: deep navy brand gradient + “wet glass” noise + warm amber bokeh (reference mood).
          No external bitmap required — fast and works offline.
        */
        .login-hero {
            --brand-deep: #002855;
            --brand-mid: #003d7a;
            background:
                radial-gradient(ellipse 85% 75% at 50% 45%, rgba(0, 82, 162, 0.55) 0%, rgba(0, 61, 122, 0.35) 42%, transparent 72%),
                linear-gradient(165deg, #010816 0%, var(--brand-deep) 32%, var(--brand-mid) 58%, #061a33 85%, #02050c 100%);
        }
        .login-hero__bokeh {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse 100% 70% at 12% 8%, rgba(0, 82, 162, 0.45), transparent 52%),
                radial-gradient(ellipse 80% 55% at 88% 12%, rgba(0, 61, 122, 0.35), transparent 48%),
                radial-gradient(ellipse 55% 45% at 72% 78%, rgba(245, 158, 11, 0.11), transparent 42%),
                radial-gradient(ellipse 45% 40% at 18% 92%, rgba(251, 191, 36, 0.08), transparent 46%),
                radial-gradient(ellipse 35% 30% at 50% 40%, rgba(0, 82, 162, 0.15), transparent 50%);
        }
        .login-hero__glass {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0.14;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            mix-blend-mode: overlay;
        }
        .login-hero__vignette {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.35) 0%, transparent 35%, transparent 65%, rgba(0, 0, 0, 0.5) 100%);
        }
    </style>
</head>
<body class="min-h-full bg-white font-sans antialiased [color-scheme:light]">
    <div class="flex min-h-full flex-col lg:min-h-screen lg:flex-row">
        {{-- Left: brand panel — centered lockup, no menu / social chrome --}}
        <aside class="login-hero relative isolate flex min-h-[min(52vh,440px)] w-full flex-col overflow-hidden text-white lg:min-h-screen lg:w-[56%] lg:max-w-[760px] lg:shrink-0">
            <div class="login-hero__bokeh" aria-hidden="true"></div>
            <div class="login-hero__glass" aria-hidden="true"></div>
            <div class="login-hero__vignette" aria-hidden="true"></div>

            <div class="relative z-10 flex flex-1 flex-col items-center justify-center px-10 py-16 text-center sm:px-14 lg:px-16 lg:py-12">
                <div class="flex w-full max-w-xl flex-col items-center gap-8">
                    <div class="flex flex-col items-center gap-6 sm:flex-row sm:justify-center sm:gap-6">
                        <div class="flex size-[4.25rem] shrink-0 items-center justify-center rounded-2xl bg-white shadow-xl shadow-black/35 ring-2 ring-white/35">
                            <svg class="size-10 text-brand-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.65" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                            </svg>
                        </div>
                        <span class="text-4xl font-bold tracking-tight text-white sm:text-5xl">{{ config('app.name', 'CruLynk') }}</span>
                    </div>
                    <p class="max-w-lg text-[15px] leading-relaxed text-white/75 sm:text-base">
                        {{ config('app.name', 'CruLynk') }} registration portal — sign in to review applications for your organization.
                    </p>
                </div>
            </div>
        </aside>

        {{-- Right: form (reference: white panel, soft grey labels, inset icons, brand CTA) --}}
        <main class="relative flex flex-1 flex-col justify-center bg-white px-5 py-12 sm:px-10 lg:px-14 xl:px-20">
            <div class="relative mx-auto w-full max-w-md">
                <h1 class="text-3xl font-bold tracking-tight text-brand-text sm:text-[2rem]">Login</h1>
                <p class="mt-2 text-[15px] leading-relaxed text-brand-text-secondary">Please fill your information below.</p>

                <form method="post" action="{{ route('portal.login.store') }}" class="mt-10 space-y-6" data-portal-login>
                    @csrf

                    <div>
                        <label for="company_id" class="mb-2 block text-sm font-medium text-brand-text-secondary">Organization</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-brand-icon">
                                <svg class="size-[1.15rem]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75"/></svg>
                            </span>
                            <select
                                name="company_id"
                                id="company_id"
                                required
                                @class([
                                    'block w-full appearance-none rounded-xl border bg-white py-3.5 pl-11 pr-10 text-[15px] font-medium text-brand-text shadow-sm outline-none transition focus:ring-4',
                                    'border-brand-border focus:border-brand-primary-light focus:ring-brand-primary-light/20' => ! $errors->has('company_id'),
                                    'border-brand-primary-light ring-4 ring-brand-primary-light/20' => $errors->has('company_id'),
                                ])
                            >
                                <option value="" disabled {{ old('company_id') ? '' : 'selected' }}>Choose your organization</option>
                                @if ($platformCompany)
                                    <option value="{{ $platformCompany->id }}" @selected(old('company_id') == $platformCompany->id)>{{ $platformCompany->name }} (Platform)</option>
                                    @if ($companies->isNotEmpty())
                                        <option disabled>──────────</option>
                                    @endif
                                @endif
                                @foreach ($companies as $org)
                                    <option value="{{ $org->id }}" @selected(old('company_id') == $org->id)>{{ $org->name }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex w-10 items-center justify-center text-brand-icon">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-brand-text-secondary">Email</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-brand-icon">
                                <svg class="size-[1.15rem]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                            </span>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                value="{{ old('email') }}"
                                required
                                autocomplete="username"
                                inputmode="email"
                                @class([
                                    'block w-full rounded-xl border bg-white py-3.5 pl-11 pr-4 text-[15px] text-brand-text shadow-sm outline-none placeholder:text-brand-icon focus:ring-4',
                                    'border-brand-border focus:border-brand-primary-light focus:ring-brand-primary-light/20' => ! $errors->has('email') && ! $errors->has('login'),
                                    'border-brand-primary-light ring-4 ring-brand-primary-light/20' => $errors->has('email') || $errors->has('login'),
                                ])
                                placeholder="you@company.com"
                            />
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <label for="password" class="block text-sm font-medium text-brand-text-secondary">Password</label>
                            <span class="text-xs font-semibold text-brand-primary-light">Forgot password?</span>
                        </div>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-brand-icon">
                                <svg class="size-[1.15rem]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            </span>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                autocomplete="current-password"
                                @class([
                                    'block w-full rounded-xl border bg-white py-3.5 pl-11 pr-4 text-[15px] text-brand-text shadow-sm outline-none focus:ring-4',
                                    'border-brand-border focus:border-brand-primary-light focus:ring-brand-primary-light/20' => ! $errors->has('password') && ! $errors->has('login'),
                                    'border-brand-primary-light ring-4 ring-brand-primary-light/20' => $errors->has('password') || $errors->has('login'),
                                ])
                            />
                        </div>
                        <p class="mt-1.5 text-xs text-brand-text-secondary">Password resets are handled by your organization. Contact an administrator if you are locked out.</p>
                    </div>

                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="checkbox" name="remember" id="remember" value="1" class="size-4 rounded border-brand-border text-brand-primary focus:ring-brand-primary-light/35" {{ old('remember') ? 'checked' : '' }} />
                        <span class="text-sm text-brand-text-secondary">Stay signed in on this device</span>
                    </label>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-primary-light px-8 py-3.5 text-[15px] font-semibold text-white shadow-lg shadow-brand-primary-light/30 transition hover:bg-brand-primary focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-primary-light/35 active:scale-[0.98]"
                        >
                            Next
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </button>
                    </div>
                </form>

                <div class="mt-12 border-t border-brand-border pt-8">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <p class="text-sm text-brand-text-secondary">Need access for your team?</p>
                        <p class="text-sm font-semibold text-brand-link">Contact your organization administrator.</p>
                    </div>
                </div>
            </div>
            @include('partials.cru-lynk-flash', ['validationErrorTitle' => 'Sign in failed'])
        </main>
    </div>
    @if (isset($errors) && $errors->any())
        <script>
            (function () {
                var attempts = 0;
                function tryShowLoginValidation() {
                    if (window.__portalValidationShown) {
                        return;
                    }
                    var payload = document.getElementById('portal-validation-payload');
                    if (!payload || !window.CruLynkDialog) {
                        if (attempts++ < 40) {
                            setTimeout(tryShowLoginValidation, 50);
                        }
                        return;
                    }
                    window.__portalValidationShown = true;
                    if (typeof window.CruLynkDialog.alertValidationErrors === 'function') {
                        try {
                            var data = JSON.parse(payload.textContent || '{}');
                            var errors = Array.isArray(data.errors) ? data.errors : [];
                            var title = data.title || 'Sign in failed';
                            payload.remove();
                            if (errors.length === 1 && typeof window.CruLynkDialog.alert === 'function') {
                                window.CruLynkDialog.alert({
                                    title: title,
                                    text: errors[0],
                                    icon: 'error',
                                    confirmText: 'Try again',
                                }).then(function () {
                                    (document.getElementById('password') || document.getElementById('email'))?.focus();
                                });
                            } else if (errors.length > 0) {
                                window.CruLynkDialog.alertValidationErrors(title, errors);
                            }
                        } catch (e) {
                            /* initCruLynkDialogs handles payload if parse fails */
                        }
                    }
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', tryShowLoginValidation);
                } else {
                    tryShowLoginValidation();
                }
            })();
        </script>
    @endif
</body>
</html>

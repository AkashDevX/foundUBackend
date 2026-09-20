{{--
  Organization portal login - split layout inspired by modern auth UIs (ELBOD2i-style reference).
  Colors from app mobile / web brand: --color-brand-primary (#003d7a), primary-dark (#002855),
  primary-light (#0052a2); body text/links align with admin theme.
  Visual-only motion: login fields, names, routes, and validation are unchanged.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-hidden">
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
    <title>Login - {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/crulynk-logo.png') }}?v=9" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/crulynk-logo.png') }}?v=9">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --login-lime: #99d31a;
            --login-lime-soft: rgba(153, 211, 26, 0.45);
        }
        html, body {
            height: 100%;
            max-height: 100dvh;
            overflow: hidden;
        }
        /* SweetAlert2 must not collapse the fixed login viewport */
        html.swal2-shown,
        html.swal2-height-auto,
        body.swal2-shown,
        body.swal2-height-auto {
            height: 100% !important;
            max-height: 100dvh !important;
            overflow: hidden !important;
        }
        .login-shell {
            height: 100%;
            max-height: 100dvh;
            overflow: hidden;
        }
        html.swal2-shown .login-shell,
        body.swal2-shown .login-shell {
            height: 100% !important;
            max-height: 100dvh !important;
            min-height: 100dvh;
        }
        .login-hero {
            flex: 0 0 auto;
            height: 34vh;
            max-height: 250px;
            min-height: 0;
            overflow: hidden;
            z-index: 2;
            /* Bottom wave meets left + right corners so no white gaps */
            -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' preserveAspectRatio='none'%3E%3Cpath fill='white' d='M0 0 H100 V100 C72 88 28 88 0 100 Z'/%3E%3C/svg%3E");
            -webkit-mask-size: 100% 100%;
            -webkit-mask-repeat: no-repeat;
            mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' preserveAspectRatio='none'%3E%3Cpath fill='white' d='M0 0 H100 V100 C72 88 28 88 0 100 Z'/%3E%3C/svg%3E");
            mask-size: 100% 100%;
            mask-repeat: no-repeat;
        }
        .login-panel {
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            z-index: 1;
        }
        .login-hero__cut {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 4;
        }
        .login-hero__cut--side {
            display: none;
        }
        .login-hero__cut--bottom {
            display: block;
        }
        @media (min-width: 1024px) {
            .login-hero {
                height: 100%;
                max-height: none;
                flex: 0 0 54%;
                width: 54%;
                max-width: none;
                margin-right: -3.25rem;
                /* Mild side wave — meets top/bottom right corners, no white triangle */
                -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' preserveAspectRatio='none'%3E%3Cpath fill='white' d='M0 0 H100 C94 30 94 70 100 100 H0 Z'/%3E%3C/svg%3E");
                mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' preserveAspectRatio='none'%3E%3Cpath fill='white' d='M0 0 H100 C94 30 94 70 100 100 H0 Z'/%3E%3C/svg%3E");
            }
            .login-hero__cut--side {
                display: block;
            }
            .login-hero__cut--bottom {
                display: none;
            }
        }

        .login-hero {
            --brand-deep: #002855;
            --brand-mid: #003d7a;
            --spot-x: 50%;
            --spot-y: 42%;
            background:
                radial-gradient(ellipse 85% 75% at 50% 45%, rgba(0, 82, 162, 0.55) 0%, rgba(0, 61, 122, 0.35) 42%, transparent 72%),
                linear-gradient(165deg, #010816 0%, var(--brand-deep) 32%, var(--brand-mid) 58%, #061a33 85%, #02050c 100%);
            animation: none;
        }
        .login-hero__spot {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: radial-gradient(420px 420px at var(--spot-x) var(--spot-y), rgba(153, 211, 26, 0.16), transparent 58%);
            transition: background 0.45s ease;
        }
        .login-hero__aurora {
            position: absolute;
            inset: -35%;
            pointer-events: none;
            opacity: 0.42;
            background: conic-gradient(from 140deg, transparent 0deg, rgba(153, 211, 26, 0.16) 50deg, transparent 90deg, rgba(0, 82, 162, 0.28) 180deg, transparent 230deg, rgba(153, 211, 26, 0.1) 300deg, transparent 360deg);
            filter: blur(28px);
            animation: none;
        }
        .login-hero__bokeh {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse 100% 70% at 12% 8%, rgba(0, 82, 162, 0.45), transparent 52%),
                radial-gradient(ellipse 80% 55% at 88% 12%, rgba(0, 61, 122, 0.35), transparent 48%),
                radial-gradient(ellipse 55% 45% at 72% 78%, rgba(245, 158, 11, 0.11), transparent 42%),
                radial-gradient(ellipse 45% 40% at 18% 92%, rgba(153, 211, 26, 0.14), transparent 46%),
                radial-gradient(ellipse 35% 30% at 50% 40%, rgba(0, 82, 162, 0.15), transparent 50%);
            animation: loginAmbientDrift 48s ease-in-out infinite alternate;
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
        .login-hero__grid {
            position: absolute;
            inset: -20%;
            pointer-events: none;
            opacity: 0.22;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.07) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 62% 58% at 50% 48%, #000 20%, transparent 78%);
            animation: none;
        }
        .login-hero__stars {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0.55;
            background-image:
                radial-gradient(1.6px 1.6px at 12% 22%, rgba(255, 255, 255, 0.7), transparent),
                radial-gradient(1.2px 1.2px at 28% 68%, rgba(153, 211, 26, 0.7), transparent),
                radial-gradient(1.4px 1.4px at 46% 18%, rgba(255, 255, 255, 0.45), transparent),
                radial-gradient(1px 1px at 62% 76%, rgba(255, 255, 255, 0.55), transparent),
                radial-gradient(1.8px 1.8px at 78% 34%, rgba(153, 211, 26, 0.55), transparent),
                radial-gradient(1.1px 1.1px at 88% 62%, rgba(255, 255, 255, 0.5), transparent),
                radial-gradient(1.3px 1.3px at 18% 86%, rgba(255, 255, 255, 0.4), transparent),
                radial-gradient(1.5px 1.5px at 54% 48%, rgba(153, 211, 26, 0.35), transparent),
                radial-gradient(1.2px 1.2px at 8% 48%, rgba(255, 255, 255, 0.45), transparent),
                radial-gradient(1.7px 1.7px at 92% 18%, rgba(153, 211, 26, 0.4), transparent);
            animation: none;
        }
        .login-hero__net {
            position: absolute;
            inset: 8% 6% 18%;
            width: auto;
            height: auto;
            pointer-events: none;
            opacity: 0.35;
        }
        .login-hero__net path {
            fill: none;
            stroke: rgba(153, 211, 26, 0.35);
            stroke-width: 1;
            stroke-dasharray: none;
            animation: none;
        }
        .login-hero__net circle {
            fill: rgba(255, 255, 255, 0.55);
        }
        .login-hero__wave {
            position: absolute;
            left: 0;
            right: 0;
            bottom: -2px;
            height: 88px;
            pointer-events: none;
            opacity: 0.55;
        }
        .login-hero__wave path {
            fill: rgba(255, 255, 255, 0.06);
        }
        .login-hero__wave path:last-child {
            fill: rgba(153, 211, 26, 0.08);
            animation: none;
        }
        .login-orb {
            position: absolute;
            border-radius: 9999px;
            pointer-events: none;
            filter: blur(2px);
        }
        .login-orb--1 {
            width: 280px;
            height: 280px;
            left: -80px;
            top: 12%;
            background: radial-gradient(circle, rgba(0, 82, 162, 0.45) 0%, transparent 70%);
            animation: none;
        }
        .login-orb--2 {
            width: 220px;
            height: 220px;
            right: -50px;
            top: 58%;
            background: radial-gradient(circle, rgba(153, 211, 26, 0.22) 0%, transparent 70%);
            animation: none;
        }
        .login-orb--3 {
            width: 160px;
            height: 160px;
            left: 38%;
            bottom: -40px;
            background: radial-gradient(circle, rgba(0, 82, 162, 0.35) 0%, transparent 72%);
            animation: none;
        }
        .login-orb--4 {
            width: 90px;
            height: 90px;
            right: 18%;
            top: 16%;
            background: radial-gradient(circle, rgba(153, 211, 26, 0.28) 0%, transparent 70%);
            animation: none;
        }

        .login-logo-wrap {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: min(150px, 28vh, 52vw);
            height: min(150px, 28vh, 52vw);
            animation: loginReveal 0.7s ease both;
        }
        @media (min-width: 1024px) {
            .login-logo-wrap {
                width: min(320px, 54vh);
                height: min(320px, 54vh);
            }
        }
        .login-form > input[type="hidden"] {
            animation: none !important;
        }
        .login-logo-wrap::before,
        .login-logo-wrap::after {
            content: "";
            position: absolute;
            border-radius: 9999px;
            border: 1px solid rgba(153, 211, 26, 0.28);
            pointer-events: none;
        }
        .login-logo-wrap::before {
            width: 108%;
            height: 108%;
            animation: none;
        }
        .login-logo-wrap::after {
            width: 132%;
            height: 132%;
            border-color: rgba(255, 255, 255, 0.12);
            animation: none;
        }
        .login-orbit {
            position: absolute;
            inset: 4%;
            border-radius: 9999px;
            border: 1px dashed rgba(255, 255, 255, 0.16);
            pointer-events: none;
            animation: none;
        }
        .login-orbit--slow {
            inset: -8%;
            border-style: dotted;
            border-color: rgba(153, 211, 26, 0.22);
            animation: none;
        }
        .login-orbit__dot {
            position: absolute;
            top: 0;
            left: 50%;
            width: 8px;
            height: 8px;
            border-radius: 9999px;
            background: var(--login-lime);
            box-shadow: 0 0 14px rgba(153, 211, 26, 0.9);
            transform: translate(-50%, -50%);
        }
        .login-orbit--slow .login-orbit__dot {
            width: 6px;
            height: 6px;
            background: #fff;
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.7);
            top: 28%;
        }
        .login-logo {
            position: relative;
            z-index: 1;
            animation: none;
            filter: drop-shadow(0 18px 36px rgba(0, 0, 0, 0.35)) drop-shadow(0 0 28px rgba(153, 211, 26, 0.18));
        }
        .login-badge {
            animation: loginReveal 0.65s ease 0.12s both;
        }
        .login-badge__dot {
            animation: none;
        }
        .login-pills {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.45rem;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            animation: loginReveal 0.65s ease 0.2s both;
        }
        .login-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            flex: 0 1 auto;
            border-radius: 9999px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            padding: 0.32rem 0.7rem;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(10px);
        }
        @media (max-width: 639px) {
            .login-panel {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            .login-panel__card {
                padding: 1.1rem 1rem;
                width: 100%;
                max-width: 100%;
                align-self: stretch;
            }
            .login-pills {
                gap: 0.28rem;
                transform: scale(0.84);
                transform-origin: top center;
            }
            .login-pill {
                font-size: 8px;
                letter-spacing: 0.07em;
                padding: 0.26rem 0.45rem;
            }
        }
        .login-pill span {
            width: 6px;
            height: 6px;
            border-radius: 9999px;
            background: var(--login-lime);
            box-shadow: 0 0 8px rgba(153, 211, 26, 0.7);
            animation: none;
        }
        .login-pill:nth-child(2) span { animation: none; background: #60a5fa; box-shadow: 0 0 8px rgba(96, 165, 250, 0.7); }
        .login-pill:nth-child(3) span { animation: none; background: #fbbf24; box-shadow: 0 0 8px rgba(251, 191, 36, 0.55); }

        .login-panel,
        .login-panel * {
            box-sizing: border-box;
        }
        .login-panel {
            overflow-x: hidden;
            background:
                radial-gradient(1200px 420px at -8% 12%, rgba(0, 82, 162, 0.08), transparent 55%),
                radial-gradient(700px 280px at 108% 92%, rgba(153, 211, 26, 0.08), transparent 50%),
                linear-gradient(180deg, #f5f8fc 0%, #ffffff 38%, #ffffff 100%);
        }
        .login-panel__glow {
            position: absolute;
            width: 280px;
            height: 280px;
            right: -80px;
            top: 8%;
            border-radius: 9999px;
            pointer-events: none;
            background: radial-gradient(circle, rgba(0, 82, 162, 0.1), transparent 68%);
            animation: none;
        }
        .login-panel__glow--lime {
            left: -90px;
            right: auto;
            bottom: 6%;
            top: auto;
            background: radial-gradient(circle, rgba(153, 211, 26, 0.12), transparent 68%);
            animation: none;
        }
        .login-panel__card {
            position: relative;
            width: 100%;
            max-width: 28rem;
            min-width: 0;
            align-self: center;
            box-sizing: border-box;
            padding: 1.15rem 1.15rem;
            border-radius: 1.75rem;
            background: rgba(255, 255, 255, 0.78);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.9) inset,
                0 24px 60px rgba(0, 61, 122, 0.08);
            backdrop-filter: blur(18px);
            animation: loginReveal 0.55s ease both;
        }
        .login-panel__card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            pointer-events: none;
            padding: 1px;
            background: linear-gradient(160deg, rgba(0, 82, 162, 0.28), rgba(153, 211, 26, 0.28), rgba(0, 82, 162, 0.12));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask-composite: exclude;
            animation: none;
        }
        @media (min-width: 1024px) {
            .login-panel__card {
                padding: 1.6rem 1.75rem;
                max-width: 28rem;
            }
        }
        .login-lede {
            display: block;
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        .login-title-accent {
            width: 3rem;
            height: 3px;
            border-radius: 9999px;
            background: linear-gradient(90deg, #0052a2, var(--login-lime));
            animation: loginReveal 0.55s ease 0.08s both;
        }
        .login-form {
            min-width: 0;
            max-width: 100%;
        }
        .login-form > * + * {
            margin-top: 0.9rem;
        }
        .login-form > * {
            animation: loginReveal 0.45s ease both;
        }
        .login-form > *:nth-child(1) { animation-delay: 0.12s; }
        .login-form > *:nth-child(2) { animation-delay: 0.2s; }
        .login-form > *:nth-child(3) { animation-delay: 0.28s; }
        .login-form > *:nth-child(4) { animation-delay: 0.36s; }
        .login-form > *:nth-child(5) { animation-delay: 0.44s; }
        .login-form > *:nth-child(6) { animation-delay: 0.52s; }

        .login-field {
            min-width: 0;
            max-width: 100%;
            transition: transform 0.25s ease;
        }
        .login-field:focus-within {
            transform: translateY(-1px);
        }
        .login-field__icon {
            transition: color 0.22s ease, transform 0.22s ease;
        }
        .login-field:focus-within .login-field__icon {
            color: #0052a2;
            transform: scale(1.08);
        }
        .login-control {
            width: 100%;
            max-width: 100%;
            min-width: 0 !important;
            background: linear-gradient(#fff, #fff) padding-box;
            transition: border-color 0.22s ease, box-shadow 0.22s ease, background-color 0.22s ease;
        }
        .login-field > div {
            min-width: 0;
            max-width: 100%;
            width: 100%;
            overflow: hidden;
        }
        .login-control:hover {
            box-shadow: 0 8px 20px rgba(0, 61, 122, 0.07);
        }
        .login-check {
            accent-color: #003d7a;
            transition: transform 0.15s ease;
        }
        .login-check:hover {
            transform: scale(1.08);
        }
        .login-btn {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            width: 100%;
            min-width: 148px;
            background: linear-gradient(180deg, #1a6bb8 0%, #0052a2 55%, #003d7a 100%);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
            animation: none;
        }
        .login-btn::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(105deg, transparent 35%, rgba(255, 255, 255, 0.28) 50%, transparent 65%);
            transform: translateX(-120%);
            animation: none;
            opacity: 0;
        }
        .login-btn:hover {
            filter: brightness(1.06);
            box-shadow: 0 16px 32px rgba(0, 82, 162, 0.32);
            transform: translateY(-1px);
            animation: none;
        }
        .login-btn:hover .login-btn__arrow {
            transform: translateX(3px);
        }
        .login-btn__arrow {
            transition: transform 0.22s ease;
        }
        .login-secure {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            margin-top: 1.5rem;
            color: #94a3b8;
            font-size: 12px;
            animation: loginReveal 0.5s ease 0.25s both;
        }

        @keyframes loginAmbientDrift {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(1.2%, -0.8%, 0); }
        }
        @keyframes loginReveal {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1023px) {
            .login-badge {
                display: none;
            }
            .login-secure {
                display: none;
            }
            .login-control {
                padding-top: 0.7rem;
                padding-bottom: 0.7rem;
            }
            .login-btn {
                padding-top: 0.7rem;
                padding-bottom: 0.7rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-hero,
            .login-hero__bokeh,
            .login-hero__grid,
            .login-hero__stars,
            .login-hero__aurora,
            .login-hero__net path,
            .login-hero__wave path,
            .login-orb,
            .login-orbit,
            .login-logo-wrap,
            .login-logo-wrap::before,
            .login-logo-wrap::after,
            .login-logo,
            .login-badge,
            .login-badge__dot,
            .login-pills,
            .login-pill span,
            .login-panel__card,
            .login-panel__card::before,
            .login-panel__glow,
            .login-title-accent,
            .login-form > *,
            .login-btn,
            .login-btn::before,
            .login-secure {
                animation: none !important;
            }
            .login-field:focus-within,
            .login-btn:hover,
            .login-check:hover {
                transform: none;
            }
        }
    </style>
</head>
<body class="h-full overflow-hidden bg-white font-sans antialiased [color-scheme:light]">
    <div class="login-shell flex h-full flex-col lg:flex-row">
        {{-- Left: brand panel - centered lockup, no menu / social chrome --}}
        <aside class="login-hero relative isolate flex w-full flex-col overflow-hidden text-white lg:shrink-0">
            <div class="login-hero__aurora" aria-hidden="true"></div>
            <div class="login-hero__bokeh" aria-hidden="true"></div>
            <div class="login-hero__spot" aria-hidden="true"></div>
            <div class="login-hero__grid" aria-hidden="true"></div>
            <div class="login-hero__stars" aria-hidden="true"></div>
            <svg class="login-hero__net" viewBox="0 0 600 700" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <path d="M70 160 C 180 90, 280 210, 390 140 S 560 220, 540 320" />
                <path d="M90 420 C 200 360, 260 500, 410 430 S 530 390, 560 510" />
                <circle cx="70" cy="160" r="3" />
                <circle cx="390" cy="140" r="2.5" />
                <circle cx="540" cy="320" r="3" />
                <circle cx="90" cy="420" r="2.5" />
                <circle cx="410" cy="430" r="3" />
            </svg>
            <div class="login-orb login-orb--1" aria-hidden="true"></div>
            <div class="login-orb login-orb--2" aria-hidden="true"></div>
            <div class="login-orb login-orb--3" aria-hidden="true"></div>
            <div class="login-orb login-orb--4" aria-hidden="true"></div>
            <div class="login-hero__glass" aria-hidden="true"></div>
            <div class="login-hero__vignette" aria-hidden="true"></div>
            <svg class="login-hero__cut login-hero__cut--side" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                <path d="M100 0 C94 30 94 70 100 100" fill="none" stroke="rgba(153,211,26,0.35)" stroke-width="0.35" />
            </svg>
            <svg class="login-hero__cut login-hero__cut--bottom" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 100 C28 88 72 88 100 100" fill="none" stroke="rgba(153,211,26,0.35)" stroke-width="0.45" />
            </svg>

            <div class="login-hero-lockup relative z-10 flex flex-1 flex-col items-center justify-center px-5 py-3 pb-8 text-center sm:px-10 lg:px-10 lg:py-8 lg:pb-8">
                <div class="flex w-full max-w-2xl flex-col items-center gap-3 sm:gap-5">
                    <div class="login-logo-wrap">
                        <span class="login-orbit" aria-hidden="true"><span class="login-orbit__dot"></span></span>
                        <span class="login-orbit login-orbit--slow" aria-hidden="true"><span class="login-orbit__dot"></span></span>
                        <img
                            src="{{ asset('images/crulynk-logo.png') }}?v=9"
                            alt="{{ config('app.name', 'CruLynk') }}"
                            width="240"
                            height="205"
                            style="width: 210px; max-width: 78%; height: auto;"
                            class="login-logo mx-auto object-contain"
                        >
                    </div>
                    <div class="login-badge inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/[0.08] px-4 py-2 shadow-[0_8px_30px_rgba(0,0,0,0.18)] backdrop-blur-md">
                        <span class="login-badge__dot size-1.5 shrink-0 rounded-full bg-[#99d31a] shadow-[0_0_10px_rgba(153,211,26,0.65)]" aria-hidden="true"></span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/85">
                            Built for your team.
                        </span>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Right: form (reference: white panel, soft grey labels, inset icons, brand CTA) --}}
        <main class="login-panel relative flex flex-1 flex-col justify-center px-4 py-4 sm:px-6 lg:pl-10 lg:pr-12 lg:py-6">
            <div class="login-panel__glow" aria-hidden="true"></div>
            <div class="login-panel__glow login-panel__glow--lime" aria-hidden="true"></div>
            <div class="login-panel__card relative mx-auto w-full min-w-0">
                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-primary-light">Organization portal</p>
                <h1 class="text-3xl font-bold tracking-tight text-brand-text sm:text-[2rem]">Login</h1>
                <div class="login-title-accent mt-2" aria-hidden="true"></div>
                <p class="login-lede mt-2 text-sm leading-relaxed text-brand-text-secondary">Welcome back. Sign in to your workspace.</p>

                <form method="post" action="{{ route('portal.login.store') }}" class="login-form mt-5" data-portal-login>
                    @csrf

                    <div class="login-field">
                        <label for="company_id" class="mb-1.5 block text-sm font-medium text-brand-text-secondary">Organization</label>
                        <div class="relative">
                            <span class="login-field__icon pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-brand-icon">
                                <svg class="size-[1.15rem]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75"/></svg>
                            </span>
                            <select
                                name="company_id"
                                id="company_id"
                                required
                                @class([
                                    'login-control block w-full appearance-none rounded-xl border bg-white py-3.5 pl-11 pr-10 text-[15px] font-medium text-brand-text shadow-sm outline-none transition focus:ring-4',
                                    'border-brand-border focus:border-brand-primary-light focus:ring-brand-primary-light/20' => ! $errors->has('company_id'),
                                    'border-brand-primary-light ring-4 ring-brand-primary-light/20' => $errors->has('company_id'),
                                ])
                            >
                                <option value="" disabled {{ old('company_id') ? '' : 'selected' }}>Choose your organization</option>
                                @if ($platformCompany)
                                    <option value="{{ $platformCompany->id }}" @selected(old('company_id') == $platformCompany->id)>{{ $platformCompany->name }} (Platform)</option>
                                    @if ($companies->isNotEmpty())
                                        <option disabled>----------</option>
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

                    <div class="login-field">
                        <label for="email" class="mb-1.5 block text-sm font-medium text-brand-text-secondary">Email</label>
                        <div class="relative">
                            <span class="login-field__icon pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-brand-icon">
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
                                    'login-control block w-full rounded-xl border bg-white py-3.5 pl-11 pr-4 text-[15px] text-brand-text shadow-sm outline-none placeholder:text-brand-icon focus:ring-4',
                                    'border-brand-border focus:border-brand-primary-light focus:ring-brand-primary-light/20' => ! $errors->has('email') && ! $errors->has('login'),
                                    'border-brand-primary-light ring-4 ring-brand-primary-light/20' => $errors->has('email') || $errors->has('login'),
                                ])
                                placeholder="you@company.com"
                            />
                        </div>
                    </div>

                    <div class="login-field">
                        <label for="password" class="mb-1.5 block text-sm font-medium text-brand-text-secondary">Password</label>
                        <div class="relative">
                            <span class="login-field__icon pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-brand-icon">
                                <svg class="size-[1.15rem]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            </span>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                autocomplete="current-password"
                                @class([
                                    'login-control block w-full rounded-xl border bg-white py-3.5 pl-11 pr-4 text-[15px] text-brand-text shadow-sm outline-none focus:ring-4',
                                    'border-brand-border focus:border-brand-primary-light focus:ring-brand-primary-light/20' => ! $errors->has('password') && ! $errors->has('login'),
                                    'border-brand-primary-light ring-4 ring-brand-primary-light/20' => $errors->has('password') || $errors->has('login'),
                                ])
                            />
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="checkbox" name="remember" id="remember" value="1" class="login-check size-4 rounded border-brand-border text-brand-primary focus:ring-brand-primary-light/35" {{ old('remember') ? 'checked' : '' }} />
                        <span class="text-sm text-brand-text-secondary">Stay signed in on this device</span>
                    </label>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="login-btn inline-flex w-full items-center justify-center gap-2 rounded-xl px-8 py-3.5 text-[15px] font-semibold text-white shadow-lg shadow-brand-primary-light/30 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-primary-light/35 active:scale-[0.98]"
                        >
                            Next
                            <svg class="login-btn__arrow size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </button>
                    </div>
                </form>
                <p class="login-secure">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    Secure organization access
                </p>
            </div>
            @include('partials.cru-lynk-flash', ['validationErrorTitle' => 'Sign in failed'])
        </main>
    </div>
    <script>
        (function () {
            var hero = document.querySelector('.login-hero');
            if (!hero || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }
            var move = function (event) {
                var box = hero.getBoundingClientRect();
                var x = ((event.clientX - box.left) / box.width) * 100;
                var y = ((event.clientY - box.top) / box.height) * 100;
                hero.style.setProperty('--spot-x', x.toFixed(2) + '%');
                hero.style.setProperty('--spot-y', y.toFixed(2) + '%');
            };
            hero.addEventListener('pointermove', move);
        })();
    </script>
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

@extends('layouts.admin')

@php
    /** @var string $section */
    $pageTitle = match ($section) {
        'rates' => 'Award rates',
        'holidays' => 'Public holidays',
        default => 'Payrun',
    };
    $pageSubheading = match ($section) {
        'rates' => $company->name.' — configure award pay rates used in payroll calculations.',
        'holidays' => $company->name.' — manage public holidays for penalty rate calculations.',
        default => $company->name.' — fortnightly pay runs from approved timesheets.',
    };
@endphp

@section('title', 'Payroll — '.$pageTitle)

@section('heading', $pageTitle)

@section('subheading')
    {{ $pageSubheading }}
@endsection

@section('content')
    @php
        use App\Support\AdminPayroll;
        use App\Support\PayrollRateTypes;
        $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
        $lbl = 'text-xs font-semibold uppercase tracking-wide text-brand-label';
        $moneyIn = $in.' font-mono tabular-nums text-right max-w-[8rem]';
    @endphp

    @if ($section === 'rates')
        @include('admin.partials.payroll-rates-section')
    @elseif ($section === 'holidays')
        @include('admin.partials.payroll-holidays-section')
    @else
        @include('admin.partials.payroll-runs-section')
    @endif
@endsection

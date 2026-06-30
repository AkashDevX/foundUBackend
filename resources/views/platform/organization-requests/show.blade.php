@extends('layouts.platform')

@section('title', $organizationRequest->company_name)

@section('heading', $organizationRequest->company_name)

@section('subheading')
    Organisation access request — submitted from the mobile app.
@endsection

@section('content')
    @php
        /** @var \App\Models\OrganizationRequest $organizationRequest */
        /** @var \Carbon\CarbonInterface $displayNow */
    @endphp

    <div class="mb-6">
        <a href="{{ route('platform.organization-requests.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-brand-link transition hover:text-brand-primary">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back to all requests
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border border-l-4 border-l-brand-primary px-5 py-4 sm:px-6">
                <h2 class="text-lg font-semibold text-brand-text">Company</h2>
            </div>
            <dl class="space-y-4 px-5 py-5 text-sm sm:px-6">
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-brand-text-secondary">Company name</dt>
                    <dd class="font-medium text-brand-text">{{ $organizationRequest->company_name }}</dd>
                </div>
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-brand-text-secondary">Industry</dt>
                    <dd class="font-medium text-brand-text">{{ $organizationRequest->industryLabel() }}</dd>
                </div>
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-brand-text-secondary">Number of employees</dt>
                    <dd class="font-medium text-brand-text">{{ $organizationRequest->employeeBandLabel() }}</dd>
                </div>
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-brand-text-secondary">Postcode</dt>
                    <dd class="font-medium text-brand-text">{{ $organizationRequest->postcode }}</dd>
                </div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border border-l-4 border-l-brand-primary px-5 py-4 sm:px-6">
                <h2 class="text-lg font-semibold text-brand-text">Contact</h2>
            </div>
            <dl class="space-y-4 px-5 py-5 text-sm sm:px-6">
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-brand-text-secondary">Full name</dt>
                    <dd class="font-medium text-brand-text">{{ $organizationRequest->contact_full_name }}</dd>
                </div>
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-brand-text-secondary">Email</dt>
                    <dd class="font-medium text-brand-text">
                        <a href="mailto:{{ $organizationRequest->contact_email }}" class="text-brand-link hover:text-brand-primary">{{ $organizationRequest->contact_email }}</a>
                    </dd>
                </div>
                <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="text-brand-text-secondary">Telephone</dt>
                    <dd class="font-medium text-brand-text">
                        <a href="tel:{{ preg_replace('/\s+/', '', $organizationRequest->contact_telephone) }}" class="text-brand-link hover:text-brand-primary">{{ $organizationRequest->contact_telephone }}</a>
                    </dd>
                </div>
            </dl>
        </section>
    </div>

    <section class="mt-6 overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
        <div class="border-b border-brand-border border-l-4 border-l-brand-primary px-5 py-4 sm:px-6">
            <h2 class="text-lg font-semibold text-brand-text">Request metadata</h2>
        </div>
        <dl class="grid gap-4 px-5 py-5 text-sm sm:grid-cols-2 sm:px-6">
            <div class="flex flex-col gap-1">
                <dt class="text-brand-text-secondary">Status</dt>
                <dd>
                    @if ($organizationRequest->status === \App\Models\OrganizationRequest::STATUS_PENDING)
                        <span class="inline-flex items-center rounded-full bg-brand-primary/10 px-2.5 py-1 text-xs font-semibold text-brand-primary ring-1 ring-brand-primary/20">Pending</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-brand-surface px-2.5 py-1 text-xs font-semibold text-brand-text-secondary ring-1 ring-brand-border">{{ ucfirst($organizationRequest->status) }}</span>
                    @endif
                </dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-brand-text-secondary">Source</dt>
                <dd class="font-medium text-brand-text">{{ str_replace('_', ' ', ucfirst($organizationRequest->source)) }}</dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-brand-text-secondary">Submitted</dt>
                <dd class="font-medium text-brand-text">
                    <time datetime="{{ $organizationRequest->created_at?->toIso8601String() }}">
                        {{ \App\Support\DisplayTimezone::formatDateTime($organizationRequest->created_at) }}
                    </time>
                </dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-brand-text-secondary">Request ID</dt>
                <dd class="font-mono text-xs text-brand-text-secondary">#{{ $organizationRequest->id }}</dd>
            </div>
        </dl>
    </section>
@endsection

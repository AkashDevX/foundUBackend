@extends('layouts.admin')

@php
    /** @var \App\Models\Company $company */
    /** @var \App\Models\Employee $employee */
    use App\Support\DisplayTimezone;

    $e = $employee;
@endphp

@section('title', 'Registration — '.$e->full_legal_name)

@section('heading', 'Application details')

@section('subheading')
    {{ $company->name }} · Submitted {{ DisplayTimezone::formatDateTime($e->created_at) }}
@endsection

@section('content')
    <div class="mb-8 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-link hover:underline">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Back to all requests
        </a>
        <span class="hidden text-brand-text-secondary sm:inline">·</span>
        <span class="font-mono text-xs text-brand-text-secondary">public_id {{ $e->public_id }}</span>
    </div>

    @include('admin.partials.employee-profile-detail', ['showApprovalActions' => true])
@endsection

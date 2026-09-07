@php
    /** @var \App\Models\Employee $e */
    /** @var bool $canEditProfile */
    $e->loadMissing('jobTitles');
    $jobTitles = $jobTitles ?? collect();
    $assignedTitles = $e->jobTitles;
    $assignedIds = old('job_title_ids', $assignedTitles->isNotEmpty()
        ? $assignedTitles->pluck('id')->all()
        : ($e->job_title_id ? [$e->job_title_id] : []));
    $assignedIds = array_map('strval', (array) $assignedIds);
    $registrationJobTitle = trim((string) ($e->job_title ?? ''));
    $showRegistrationNote = $assignedIds === [] && $registrationJobTitle !== '';

    $bankNumber = $e->bank_account_number;
    $bankHasAccount = $bankNumber !== null && trim((string) $bankNumber) !== '';
    $bankMasked = $bankHasAccount ? str_repeat('X', 10).substr((string) $bankNumber, -4) : '';
    $oldBankNumber = old('bank_account_number');
    $bankNumberValue = is_string($oldBankNumber) ? $oldBankNumber : '';
@endphp

<div class="{{ $dlStart ?? $dl }}">
    <dt class="font-medium text-brand-label pt-1">Job titles</dt>
    <dd class="min-w-0">
        @if ($canEditProfile)
            @if ($jobTitles->isEmpty())
                <p class="text-sm text-brand-text-secondary">No job titles yet — add them under <a href="{{ route('admin.workforce.job-titles') }}" class="font-semibold text-brand-link hover:underline">Organization setup</a>.</p>
            @else
                <div data-profile-jt-picker>
                    <button
                        type="button"
                        data-profile-jt-toggle
                        class="flex w-full items-center gap-2 rounded-xl border border-brand-border bg-white px-3 py-2.5 text-left text-sm text-brand-text shadow-inner transition hover:border-brand-primary/30 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25"
                        aria-expanded="false"
                        aria-haspopup="listbox"
                    >
                        <span class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5" data-profile-jt-label>
                            <span class="text-brand-text-secondary/70" data-profile-jt-placeholder>Select job titles</span>
                        </span>
                        <svg class="size-4 shrink-0 text-brand-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div data-profile-jt-menu class="mt-1.5 hidden overflow-hidden rounded-xl border border-brand-border bg-white shadow-xl ring-1 ring-black/[0.06]" role="listbox" aria-multiselectable="true">
                        <div class="relative border-b border-brand-border/80">
                            <input type="search" data-profile-jt-search class="w-full border-0 bg-transparent py-2.5 pl-3 pr-9 text-sm text-brand-text outline-none placeholder:text-brand-text-secondary/70 focus:ring-0" placeholder="Search job titles" autocomplete="off" aria-label="Search job titles" />
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-brand-icon">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                            </span>
                        </div>
                        <div class="max-h-56 overflow-y-auto py-1">
                            @foreach ($jobTitles as $jt)
                                @php $checked = in_array((string) $jt->id, $assignedIds, true); @endphp
                                <label
                                    data-profile-jt-option
                                    data-name="{{ $jt->name }}"
                                    data-color="{{ $jt->accentColor() }}"
                                    class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm text-brand-text transition hover:bg-brand-primary/10 {{ $checked ? 'bg-brand-primary/10 font-semibold text-brand-primary' : '' }}"
                                >
                                    <input
                                        type="checkbox"
                                        name="job_title_ids[]"
                                        value="{{ $jt->id }}"
                                        class="rounded border-brand-border text-brand-primary focus:ring-brand-primary/30"
                                        @checked($checked)
                                    >
                                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $jt->accentColor() }}" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1 truncate">{{ $jt->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p data-profile-jt-empty class="hidden px-3 py-4 text-center text-sm text-brand-text-secondary">No job titles match your search.</p>
                    </div>
                </div>
                @error('job_title_ids')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endif
            @if ($showRegistrationNote)
                <p class="mt-1 text-xs text-brand-text-secondary">Registration note: {{ $line($registrationJobTitle) }}</p>
            @endif
        @else
            @if ($assignedTitles->isEmpty())
                <span class="text-brand-text">{{ $line($registrationJobTitle !== '' ? $registrationJobTitle : null) }}</span>
            @else
                <ul class="flex flex-wrap gap-1.5">
                    @foreach ($assignedTitles as $jt)
                        <li class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-brand-surface px-2.5 py-1 text-xs font-semibold text-brand-text ring-1 ring-inset ring-brand-border">
                            <span class="size-2 shrink-0 rounded-full" style="background-color: {{ $jt->accentColor() }}" aria-hidden="true"></span>
                            <span class="truncate">{{ $jt->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </dd>
</div>

<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Bank account name</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_account_name" maxlength="160" value="{{ old('bank_account_name', $e->bank_account_name) }}" class="{{ $editIn }}" autocomplete="name" />@else<span class="text-brand-text">{{ $line($e->bank_account_name) }}</span>@endif</dd></div>
<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Bank name</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_name" maxlength="160" value="{{ old('bank_name', $e->bank_name) }}" class="{{ $editIn }}" autocomplete="off" />@else<span class="text-brand-text">{{ $line($e->bank_name) }}</span>@endif</dd></div>
<div class="{{ $dl }}"><dt class="font-medium text-brand-label">BSB</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_branch_code" maxlength="32" value="{{ old('bank_branch_code', $e->bank_branch_code) }}" class="{{ $editIn }}" inputmode="numeric" autocomplete="off" placeholder="000-000" />@else<span class="text-brand-text">{{ $line($e->bank_branch_code) }}</span>@endif</dd></div>
<div class="{{ $dl }}">
    <dt class="font-medium text-brand-label">Account number</dt>
    <dd class="min-w-0">
        @if ($canEditProfile)
            <input type="text" name="bank_account_number" maxlength="64" value="{{ $bankNumberValue }}" class="{{ $editIn }}" autocomplete="off" inputmode="numeric" placeholder="{{ $bankHasAccount ? 'Leave blank to keep current' : '' }}" />
            @if ($bankHasAccount && $bankNumberValue === '')
                <p class="mt-1 text-xs text-brand-text-secondary">Current account on file: {{ $bankMasked }}</p>
            @endif
        @else
            <span class="text-brand-text">{{ $bankHasAccount ? $bankMasked : '—' }}</span>
        @endif
    </dd>
</div>

@if ($canEditProfile && $jobTitles->isNotEmpty())
    <script>
        (function () {
            var picker = document.querySelector('[data-profile-jt-picker]');
            if (!picker || picker.getAttribute('data-ready') === '1') return;
            picker.setAttribute('data-ready', '1');

            function normalize(value) {
                return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
            }

            var toggle = picker.querySelector('[data-profile-jt-toggle]');
            var menu = picker.querySelector('[data-profile-jt-menu]');
            var search = picker.querySelector('[data-profile-jt-search]');
            var label = picker.querySelector('[data-profile-jt-label]');
            var empty = picker.querySelector('[data-profile-jt-empty]');
            var options = Array.prototype.slice.call(picker.querySelectorAll('[data-profile-jt-option]'));

            function setMenuOpen(open) {
                if (!menu || !toggle) return;
                menu.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open && search) search.focus();
            }

            function syncTitles() {
                if (!label) return;
                label.textContent = '';
                var names = 0;
                var shown = 0;
                var q = normalize(search ? search.value : '');
                options.forEach(function (option) {
                    var box = option.querySelector('input[type="checkbox"]');
                    var selected = !!(box && box.checked);
                    option.classList.toggle('bg-brand-primary/10', selected);
                    option.classList.toggle('font-semibold', selected);
                    option.classList.toggle('text-brand-primary', selected);
                    var match = q === '' || normalize(option.getAttribute('data-name')).indexOf(q) !== -1;
                    option.classList.toggle('hidden', !match);
                    if (match) shown += 1;
                    if (!selected) return;
                    names += 1;
                    var chip = document.createElement('span');
                    chip.className = 'inline-flex max-w-full items-center gap-1.5 rounded-full bg-brand-primary/10 px-2.5 py-0.5 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-brand-primary/20';
                    var dot = document.createElement('span');
                    dot.className = 'size-2 shrink-0 rounded-full';
                    dot.style.backgroundColor = option.getAttribute('data-color') || '#64748b';
                    var text = document.createElement('span');
                    text.className = 'truncate';
                    text.textContent = option.getAttribute('data-name') || '';
                    chip.appendChild(dot);
                    chip.appendChild(text);
                    label.appendChild(chip);
                });
                if (names === 0) {
                    var placeholder = document.createElement('span');
                    placeholder.className = 'text-brand-text-secondary/70';
                    placeholder.textContent = 'Select job titles';
                    label.appendChild(placeholder);
                }
                if (empty) empty.classList.toggle('hidden', shown !== 0 || options.length === 0);
            }

            if (toggle) {
                toggle.addEventListener('click', function () {
                    setMenuOpen(menu.classList.contains('hidden'));
                });
            }
            if (search) search.addEventListener('input', syncTitles);
            options.forEach(function (option) {
                var box = option.querySelector('input[type="checkbox"]');
                if (box) box.addEventListener('change', syncTitles);
            });
            document.addEventListener('click', function (event) {
                if (!picker.contains(event.target)) setMenuOpen(false);
            });
            syncTitles();
        })();
    </script>
@endif

{{--
  Compact visual icon dropdown for Chat Help FAQs.
  @var string $selected
  @var string $inputId
  @var string $fieldName
--}}
@php
    $selected = $selected ?? 'help-circle';
    $inputId = $inputId ?? 'faq-icon';
    $fieldName = $fieldName ?? 'faq_icon';
    $catalog = \App\Support\ChatFaqIcons::catalog();
    if (! isset($catalog[$selected])) {
        $selected = 'help-circle';
    }
    $selectedMeta = $catalog[$selected];
@endphp
<div class="space-y-1.5" data-faq-icon-picker>
    <label for="{{ $inputId }}-trigger" class="{{ $lbl ?? 'text-xs font-semibold uppercase tracking-wide text-brand-label' }}">Icon</label>
    <div class="relative" data-faq-icon-dropdown>
        <input type="hidden" name="{{ $fieldName }}" value="{{ $selected }}" required data-faq-icon-value>
        <button
            id="{{ $inputId }}-trigger"
            type="button"
            class="flex w-full items-center gap-3 rounded-xl border border-brand-border bg-white px-3 py-2.5 text-left text-sm text-brand-text shadow-sm transition hover:border-brand-primary/40 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
            aria-haspopup="listbox"
            aria-expanded="false"
            data-faq-icon-trigger
        >
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary" data-faq-icon-trigger-preview>
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-faq-icon-selected-svg>{!! $selectedMeta['svg'] !!}</svg>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-brand-text" data-faq-icon-selected-text>{{ $selectedMeta['label'] }}</span>
                <span class="block text-[11px] text-brand-text-secondary">Shown next to the question in the app</span>
            </span>
            <svg class="size-4 shrink-0 text-brand-text-secondary transition" data-faq-icon-chevron fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
        </button>

        <div
            class="absolute left-0 right-0 z-40 mt-2 hidden overflow-hidden rounded-xl border border-brand-border bg-white shadow-xl shadow-black/10 ring-1 ring-black/[0.04]"
            role="listbox"
            aria-label="FAQ icons"
            data-faq-icon-menu
        >
            <div class="border-b border-brand-border bg-brand-surface/40 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-brand-text-secondary">
                Choose an icon
            </div>
            <div class="max-h-56 overflow-y-auto p-2">
                <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                    @foreach ($catalog as $name => $meta)
                        <button
                            type="button"
                            role="option"
                            class="flex items-center gap-2 rounded-lg border border-transparent px-2 py-2 text-left transition hover:border-brand-primary/30 hover:bg-brand-surface/70 {{ $selected === $name ? 'border-brand-primary/40 bg-brand-primary/[0.08]' : '' }}"
                            data-faq-icon-option
                            data-value="{{ $name }}"
                            data-label="{{ $meta['label'] }}"
                            aria-selected="{{ $selected === $name ? 'true' : 'false' }}"
                        >
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-surface text-brand-text-secondary {{ $selected === $name ? '!bg-brand-primary !text-white' : '' }}" data-faq-icon-option-badge>
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $meta['svg'] !!}</svg>
                            </span>
                            <span class="truncate text-xs font-semibold text-brand-text">{{ $meta['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

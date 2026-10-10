@php
    $blocks = $blocks ?? collect();
    $scope = $scope ?? 'page';
    $kindLabels = [
        'text' => 'Text',
        'pdf' => 'PDF',
        'photo' => 'Photo',
        'video' => 'Video',
        'link' => 'Link',
        'instruction' => 'Instructions',
        'note' => 'Important note',
    ];
@endphp

<div class="space-y-3 border-t border-brand-border pt-3">
    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Slide content</p>

    @if ($blocks->isNotEmpty())
        <div class="space-y-2">
            @foreach ($blocks as $block)
                @if ($block->kind === 'photo' && filled($block->file_path))
                    <div class="relative">
                        <img src="{{ route('admin.training.blocks.file', [$module->id, $block->id]) }}" alt="" class="max-h-64 w-full rounded-xl bg-white object-contain">
                        <button type="button" data-remove-name="remove_blocks[]" data-remove-value="{{ $block->id }}" aria-label="Remove picture" class="absolute flex items-center justify-center rounded-full bg-white text-brand-text shadow hover:bg-red-50 hover:text-red-600" style="top:0.5rem;right:0.5rem;width:2rem;height:2rem;">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                @else
                    <div class="flex items-start gap-3 rounded-lg border border-brand-border bg-brand-surface/40 p-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-brand-label">{{ $kindLabels[$block->kind] ?? $block->kind }}</p>
                            <p class="truncate text-sm text-brand-text">{{ $block->label ?: $block->body ?: ($block->kind === 'pdf' ? 'PDF file' : ($block->kind === 'video' ? 'Video file' : 'File')) }}</p>
                        </div>
                        <button type="button" data-remove-name="remove_blocks[]" data-remove-value="{{ $block->id }}" class="shrink-0 text-xs font-semibold text-red-600 hover:underline">Remove</button>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    <label class="block">
        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Photos</span>
        <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" data-max-photos="5" class="block w-full text-sm text-brand-text file:mr-3 file:rounded-lg file:border-0 file:bg-brand-surface file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-text">
    </label>

    <div class="space-y-2" data-block-rows="{{ $scope }}">
        <div class="space-y-2 rounded-lg border border-dashed border-brand-border p-3" data-block-row>
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Add item</span>
                <select name="new_kind[]" class="{{ $in }}">
                    <option value="">None</option>
                    <option value="text">Text</option>
                    <option value="pdf">PDF</option>
                    <option value="video">Video</option>
                    <option value="link">Link</option>
                    <option value="instruction">Instructions</option>
                    <option value="note">Important note</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Label</span>
                <input type="text" name="new_label[]" maxlength="200" class="{{ $in }}">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Text</span>
                <textarea name="new_body[]" rows="2" class="{{ $in }}"></textarea>
            </label>
            <input type="file" name="new_file[]" accept="application/pdf,video/mp4,video/webm,video/quicktime,.pdf,.mp4,.mov,.webm" class="block w-full text-sm text-brand-text file:mr-3 file:rounded-lg file:border-0 file:bg-brand-surface file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-text">
        </div>
    </div>
    <button type="button" data-add-block="{{ $scope }}" class="text-sm font-semibold text-brand-primary hover:underline">Add another item</button>
</div>

<script>
    (function () {
        var scope = @json($scope);
        var root = document.querySelector('[data-block-rows="' + scope + '"]');
        var button = document.querySelector('[data-add-block="' + scope + '"]');
        if (!root || !button || button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        button.addEventListener('click', function () {
            var row = root.querySelector('[data-block-row]');
            if (!row) return;
            var next = row.cloneNode(true);
            next.querySelectorAll('input, textarea').forEach(function (field) {
                field.value = '';
            });
            var select = next.querySelector('select');
            if (select) select.selectedIndex = 0;
            root.appendChild(next);
        });
    })();
    document.querySelectorAll('input[data-max-photos]').forEach(function (input) {
        if (input.dataset.maxBound === '1') return;
        input.dataset.maxBound = '1';
        var max = parseInt(input.getAttribute('data-max-photos'), 10) || 5;
        input.addEventListener('change', function () {
            if (input.files && input.files.length > max) {
                input.setCustomValidity('You can upload up to ' + max + ' photos at a time.');
                input.reportValidity();
            } else {
                input.setCustomValidity('');
            }
        });
    });
    if (!window.__trainingRemoveBound) {
        window.__trainingRemoveBound = true;
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-remove-name]');
            if (!button) return;
            var form = button.closest('form');
            if (!form || button.dataset.removing === '1') return;
            button.dataset.removing = '1';
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = button.getAttribute('data-remove-name');
            input.value = button.getAttribute('data-remove-value') || '1';
            form.appendChild(input);
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }
</script>

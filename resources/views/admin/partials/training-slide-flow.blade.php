@php
    $name = function (string $key) use ($prefix): string {
        return $prefix === '' ? $key : $prefix.'['.$key.']';
    };
    $oldBase = ($prefix === '' || ! $flowOwner) ? '' : 'sections.'.$flowOwner->id.'.';
    $flow = \App\Support\TrainingSlideBlocks::editorFlow($flowOwner, $isPage);
    $imageUrl = null;
    if ($flowOwner && filled($flowOwner->image_path)) {
        $imageUrl = $isPage
            ? route('admin.training.pages.image', [$module->id, $flowOwner->id])
            : route('admin.training.sections.image', [$module->id, $slidePageId, $flowOwner->id]);
    }
    $bulletLines = [];
    if ($isPage) {
        $bulletText = (string) old('bullets', $flowOwner ? implode("\n", $flowOwner->bullets ?? []) : '');
        $bulletLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $bulletText) ?: []), fn ($line) => $line !== ''));
    }
    $titleValue = $isPage ? (string) old('title', $flowOwner->title ?? '') : '';
    $bodyValue = (string) old($oldBase.'body', $flowOwner->body ?? '');
@endphp
<div data-flow>
    @foreach ($flow as $item)
        @php
            $token = $item['token'];
            $block = $item['block'];
        @endphp
        <div class="studio-flow-item" data-flow-item data-token="{{ $token }}">
            @if ($token === 'title')
                <input class="studio-title" type="text" name="title" required maxlength="200" value="{{ $titleValue }}" placeholder="Title">
            @elseif ($token === 'body')
                <textarea class="studio-body" name="{{ $name('body') }}" rows="3" placeholder="Text">{{ $bodyValue }}</textarea>
            @elseif ($token === 'bullets')
                <div class="studio-list" data-bullet-list>
                    @foreach ($bulletLines as $line)
                        <div class="studio-bullet" data-removable>
                            <span>•</span>
                            <input type="text" data-bullet-line maxlength="300" value="{{ $line }}">
                            <button type="button" class="studio-x" data-remove-row aria-label="Remove" style="position:static;box-shadow:none;">×</button>
                        </div>
                    @endforeach
                </div>
                <textarea name="bullets" data-bullet-store hidden>@if ($flowOwner){{ implode("\n", $flowOwner->bullets ?? []) }}@endif</textarea>
            @elseif ($token === 'image' && $imageUrl)
                <div class="studio-photo">
                    <img src="{{ $imageUrl }}" alt="">
                    <div class="studio-item-tools">
                        <button type="button" class="studio-mini-btn" data-move="up" aria-label="Move up">↑</button>
                        <button type="button" class="studio-mini-btn" data-move="down" aria-label="Move down">↓</button>
                        <button type="button" class="studio-x" data-remove-name="{{ $name('remove_image') }}" data-remove-value="1" aria-label="Remove picture">×</button>
                    </div>
                </div>
            @elseif ($block)
                @php
                    $labelValue = old($oldBase.'block_label.'.$block->id, $block->label);
                    $bodyBlock = old($oldBase.'block_body.'.$block->id, $block->body);
                    $removeName = $name('remove_blocks').'[]';
                @endphp
                @if ($block->kind === 'photo' && filled($block->file_path))
                    <div class="studio-photo">
                        <img src="{{ route('admin.training.blocks.file', [$module->id, $block->id]) }}" alt="">
                        <div class="studio-item-tools">
                            <button type="button" class="studio-mini-btn" data-move="up" aria-label="Move up">↑</button>
                            <button type="button" class="studio-mini-btn" data-move="down" aria-label="Move down">↓</button>
                            <button type="button" class="studio-x" data-remove-name="{{ $removeName }}" data-remove-value="{{ $block->id }}" aria-label="Remove picture">×</button>
                        </div>
                    </div>
                @elseif ($block->kind === 'instruction' || $block->kind === 'note')
                    <div class="studio-block studio-callout {{ $block->kind === 'note' ? 'studio-callout--note' : 'studio-callout--instruction' }}">
                        <button type="button" class="studio-x" data-remove-name="{{ $removeName }}" data-remove-value="{{ $block->id }}" aria-label="Remove">×</button>
                        <p class="studio-kicker">{{ $block->kind === 'note' ? 'Important note' : 'Instructions' }}</p>
                        <input class="studio-inline" type="text" name="{{ $name('block_label') }}[{{ $block->id }}]" maxlength="200" value="{{ $labelValue }}">
                        <textarea class="studio-inline" name="{{ $name('block_body') }}[{{ $block->id }}]" rows="2">{{ $bodyBlock }}</textarea>
                    </div>
                @elseif ($block->kind === 'link')
                    <div class="studio-chip">
                        <button type="button" class="studio-x" data-remove-name="{{ $removeName }}" data-remove-value="{{ $block->id }}" aria-label="Remove">×</button>
                        <div style="flex:1;min-width:0;">
                            <p class="studio-kicker">Link</p>
                            <input class="studio-inline" type="text" inputmode="url" name="{{ $name('block_body') }}[{{ $block->id }}]" value="{{ $bodyBlock }}">
                            <input class="studio-inline" type="hidden" name="{{ $name('block_label') }}[{{ $block->id }}]" maxlength="200" value="{{ $labelValue }}">
                        </div>
                    </div>
                @elseif ($block->kind === 'text')
                    <div class="studio-block">
                        <button type="button" class="studio-x" data-remove-name="{{ $removeName }}" data-remove-value="{{ $block->id }}" aria-label="Remove">×</button>
                        <textarea class="studio-body" name="{{ $name('block_body') }}[{{ $block->id }}]" rows="2">{{ $bodyBlock }}</textarea>
                    </div>
                @elseif ($block->kind === 'pdf' || $block->kind === 'video')
                    <div class="studio-chip">
                        <button type="button" class="studio-x" data-remove-name="{{ $removeName }}" data-remove-value="{{ $block->id }}" aria-label="Remove">×</button>
                        <div style="flex:1;min-width:0;">
                            <p class="studio-kicker">{{ $block->kind === 'pdf' ? 'PDF' : 'Video' }}</p>
                            <input class="studio-inline" type="text" name="{{ $name('block_label') }}[{{ $block->id }}]" maxlength="200" value="{{ $labelValue }}">
                            @if ($block->kind === 'video' && ! filled($block->file_path))
                                <input class="studio-inline" type="url" name="{{ $name('block_body') }}[{{ $block->id }}]" value="{{ $bodyBlock }}">
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endforeach
    <input type="hidden" data-layout-store name="{{ $name('content_order') }}" value="">
</div>

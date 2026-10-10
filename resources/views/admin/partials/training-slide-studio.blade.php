@php
    $creatingSlide = ! $editingPage && ($module->pages->isEmpty() || request()->boolean('new'));
    $canvasPage = $creatingSlide ? null : ($editingPage ?: $module->pages->first());
    $openSectionId = (int) ($editingSection?->id ?? 0);
    $blocks = $canvasPage?->blocks ?? collect();
    $photoBlocks = $blocks->where('kind', 'photo')->values();
    $otherBlocks = $blocks->where('kind', '!=', 'photo')->values();
    $slideNumber = 1;
    if ($canvasPage) {
        foreach ($module->pages as $i => $listed) {
            if ((int) $listed->id === (int) $canvasPage->id) {
                $slideNumber = $i + 1;
                break;
            }
        }
    }
    $formAction = ($canvasPage && ! $creatingSlide)
        ? route('admin.training.pages.update', [$module->id, $canvasPage->id])
        : route('admin.training.pages.store', $module->id);
    $titleValue = old('title', $canvasPage->title ?? '');
    $bodyValue = old('body', $canvasPage->body ?? '');
    $imageUrl = ($canvasPage && filled($canvasPage->image_path))
        ? route('admin.training.pages.image', [$module->id, $canvasPage->id])
        : null;
    $bulletLines = [];
    $bulletText = (string) old('bullets', $canvasPage ? implode("\n", $canvasPage->bullets ?? []) : '');
    $bulletLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $bulletText) ?: []), fn ($line) => $line !== ''));
@endphp

<style>
    .studio { display: grid; grid-template-columns: 196px minmax(0, 1fr); align-items: start; border: 1px solid #e6e8ec; border-radius: 16px; background: #fff; }
    .studio-pages { display: flex; flex-direction: column; gap: 10px; max-height: calc(100vh - 6rem); padding: 14px 12px 16px; border-right: 1px solid #e6e8ec; background: #fff; position: sticky; top: 5rem; }
    .studio-pages-scroll { display: flex; flex: 1; flex-direction: column; gap: 12px; min-height: 0; overflow: auto; }
    .studio-pages-label { margin: 0; font-size: 13px; font-weight: 700; color: #111827; }
    .studio-thumb { border-radius: 10px; padding: 6px; }
    .studio-thumb.is-active { background: #f5f3ff; }
    .studio-thumb-link { display: block; text-decoration: none; color: inherit; }
    .studio-mini { display: flex; flex-direction: column; justify-content: flex-end; height: 112px; overflow: hidden; border-radius: 6px; border: 1px solid #e5e7eb; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, .06); }
    .studio-thumb.is-active .studio-mini { box-shadow: 0 0 0 2px #8b3dff; }
    .studio-mini img { width: 100%; height: 72px; object-fit: cover; background: #f3f4f6; }
    .studio-mini-title { display: block; padding: 6px 8px 8px; font-size: 11px; font-weight: 650; line-height: 1.3; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .studio-thumb-actions { display: flex; justify-content: center; gap: 2px; margin-top: 4px; }
    .studio-thumb-actions form { margin: 0; }
    .studio-mini-btn { width: 24px; height: 22px; border: 0; border-radius: 6px; background: transparent; color: #6b7280; font-size: 12px; cursor: pointer; }
    .studio-mini-btn:hover { background: #f3f4f6; color: #111827; }
    .studio-mini-btn.is-danger:hover { color: #dc2626; }
    .studio-mini-btn:disabled { opacity: .35; cursor: default; }
    .studio-add { display: flex; align-items: center; justify-content: center; height: 72px; border-radius: 8px; border: 1px dashed #d1d5db; color: #6d28d9; font-size: 13px; font-weight: 700; text-decoration: none; }
    .studio-add.is-active, .studio-add:hover { border-color: #8b3dff; background: #f5f3ff; }
    .studio-questions { margin-top: auto; padding-top: 8px; font-size: 12px; font-weight: 700; color: #003d7a; text-decoration: none; }
    .studio-workspace { display: flex; min-width: 0; flex-direction: column; background: #f0f2f5; overflow: visible; }
    .studio-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e6e8ec; }
    .studio-crumb { display: flex; align-items: center; gap: 8px; min-width: 0; font-size: 13px; font-weight: 650; color: #111827; }
    .studio-crumb a { color: #6d28d9; text-decoration: none; }
    .studio-crumb span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .studio-save { border: 0; border-radius: 8px; background: #003d7a; color: #fff; padding: 8px 16px; font-size: 14px; font-weight: 700; cursor: pointer; }
    .studio-save:hover { background: #002855; }
    .studio-tools { display: flex; gap: 2px; overflow-x: auto; padding: 6px 10px; background: #fff; border-bottom: 1px solid #e6e8ec; }
    .studio-tool { display: flex; min-width: 68px; flex-direction: column; align-items: center; gap: 4px; border: 0; border-radius: 8px; background: transparent; padding: 8px 6px; color: #374151; font-size: 11px; font-weight: 650; text-decoration: none; cursor: pointer; }
    .studio-tool:hover { background: #f5f3ff; color: #6d28d9; }
    .studio-tool svg { width: 18px; height: 18px; }
    .studio-file-hidden, .studio-file-keep { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .studio-file-keep { pointer-events: none; }
    .studio-flow-item { position: relative; }
    .studio-item-tools { position: absolute; top: 8px; right: 8px; z-index: 2; display: flex; gap: 4px; }
    .studio-item-tools .studio-x, .studio-item-tools .studio-mini-btn { position: static; background: #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, .18); }
    .studio-stage { overflow: visible; padding: 28px 16px 48px; }
    .studio-sheet { width: min(720px, 100%); min-height: 560px; margin: 0 auto; border-radius: 2px; background: #fff; padding: 40px 48px 56px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 12px 40px rgba(15, 23, 42, .08); }
    .studio-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-bottom: 18px; }
    .studio-photo { position: relative; }
    .studio-photo img { display: block; width: 100%; max-height: 320px; object-fit: contain; border-radius: 8px; background: #f3f4f6; }
    .studio-title, .studio-body, .studio-inline, .studio-bullet input { width: 100%; border: 0; border-radius: 6px; background: transparent; color: #111827; font: inherit; }
    .studio-title { display: block; margin: 0 0 8px; padding: 4px 6px; font-size: 32px; font-weight: 750; line-height: 1.15; }
    .studio-body { display: block; min-height: 72px; padding: 4px 6px; font-size: 16px; line-height: 1.5; resize: none; }
    .studio-title:hover, .studio-body:hover, .studio-bullet input:hover, .studio-inline:hover { outline: 1px dashed #d4d4d8; }
    .studio-title:focus, .studio-body:focus, .studio-bullet input:focus, .studio-inline:focus { outline: 2px solid #8b3dff; }
    .studio-list { margin: 8px 0 0; }
    .studio-bullet { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
    .studio-bullet > span { color: #8b3dff; font-size: 18px; line-height: 1; }
    .studio-bullet input { padding: 4px 6px; font-size: 16px; }
    .studio-block { position: relative; margin-top: 12px; padding-right: 32px; }
    .studio-callout { border-radius: 8px; padding: 12px 36px 12px 14px; }
    .studio-callout--instruction { background: #eff6ff; box-shadow: inset 4px 0 0 #2563eb; }
    .studio-callout--note { background: #fffbeb; box-shadow: inset 4px 0 0 #d97706; }
    .studio-kicker { margin: 0 0 4px; font-size: 11px; font-weight: 750; letter-spacing: .04em; text-transform: uppercase; color: #4b5563; }
    .studio-inline { display: block; padding: 4px 2px; font-size: 15px; }
    .studio-chip { position: relative; display: flex; align-items: center; gap: 10px; margin-top: 12px; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; }
    .studio-chip > .studio-x { position: static; flex: none; order: 2; margin-left: auto; box-shadow: none; }
    .studio-pick { display: block; margin-top: 6px; font-size: 13px; }
    .studio-x { position: absolute; top: 8px; right: 8px; z-index: 1; width: 28px; height: 28px; border: 0; border-radius: 999px; background: #fff; color: #111827; box-shadow: 0 1px 4px rgba(0, 0, 0, .18); font-size: 16px; line-height: 1; cursor: pointer; }
    .studio-x:hover { color: #dc2626; }
    .studio-toggles { margin-top: 22px; display: flex; flex-direction: column; gap: 8px; }
    .studio-toggle { display: flex; flex-direction: column; align-items: stretch; gap: 0; border: 1px solid #e5e7eb; border-radius: 10px; background: #f8fafc; padding: 0; }
    .studio-toggle-bar { display: flex; align-items: center; gap: 6px; padding: 10px 12px; }
    .studio-toggle-title { flex: 1; min-width: 0; border: 0; background: transparent; color: #111827; font-size: 15px; font-weight: 700; }
    .studio-toggle-title:focus { outline: 2px solid #8b3dff; border-radius: 6px; }
    .studio-toggle-open { width: 28px; height: 28px; border: 0; border-radius: 6px; background: transparent; color: #6b7280; cursor: pointer; }
    .studio-toggle.is-open .studio-toggle-open { transform: rotate(180deg); }
    .studio-toggle-body { display: none; padding: 0 12px 14px; }
    .studio-toggle.is-open .studio-toggle-body { display: block; }
    .studio-toggle-tools { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .studio-toggle-tools button, .studio-toggle-tools label { border: 0; border-radius: 999px; background: #fff; color: #374151; padding: 6px 10px; font-size: 12px; font-weight: 700; cursor: pointer; }
    .studio-toggle-tools button:hover, .studio-toggle-tools label:hover { background: #f5f3ff; color: #6d28d9; }
    @media (max-width: 860px) {
        .studio { grid-template-columns: 1fr; }
        .studio-pages { position: static; max-height: 280px; border-right: 0; border-bottom: 1px solid #e6e8ec; }
        .studio-sheet { padding: 24px 16px 32px; }
        .studio-title { font-size: 26px; }
    }
</style>

<div class="studio">
    <aside class="studio-pages">
        <p class="studio-pages-label">Slides</p>
        <div class="studio-pages-scroll">
        @foreach ($module->pages as $index => $page)
            <div class="studio-thumb {{ ! $creatingSlide && $canvasPage && (int) $canvasPage->id === (int) $page->id ? 'is-active' : '' }}">
                <a class="studio-thumb-link" href="{{ $moduleUrl(['step' => 'study', 'edit_page' => $page->id]) }}">
                    <span class="studio-mini">
                        @php
                            $thumbUrl = filled($page->image_path)
                                ? route('admin.training.pages.image', [$module->id, $page->id])
                                : null;
                            if (! $thumbUrl) {
                                $thumbPhoto = $page->blocks->first(fn ($block) => $block->kind === 'photo' && filled($block->file_path));
                                if ($thumbPhoto) {
                                    $thumbUrl = route('admin.training.blocks.file', [$module->id, $thumbPhoto->id]);
                                }
                            }
                        @endphp
                        @if ($thumbUrl)
                            <img src="{{ $thumbUrl }}" alt="">
                        @endif
                        <span class="studio-mini-title">{{ $index + 1 }}. {{ $page->title }}</span>
                    </span>
                </a>
                <div class="studio-thumb-actions">
                    <form method="post" action="{{ route('admin.training.pages.move', [$module->id, $page->id]) }}">
                        @csrf
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="studio-mini-btn" aria-label="Move up" @disabled($index === 0)>↑</button>
                    </form>
                    <form method="post" action="{{ route('admin.training.pages.move', [$module->id, $page->id]) }}">
                        @csrf
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="studio-mini-btn" aria-label="Move down" @disabled($index === $module->pages->count() - 1)>↓</button>
                    </form>
                    <form method="post" action="{{ route('admin.training.pages.destroy', [$module->id, $page->id]) }}">
                        @csrf
                        <button type="submit" class="studio-mini-btn is-danger" aria-label="Remove slide"
                                data-confirm="This slide will be removed."
                                data-confirm-title="Remove slide?"
                                data-confirm-confirm="Remove"
                                data-confirm-cancel="Keep"
                                data-confirm-danger="1">×</button>
                    </form>
                </div>
            </div>
        @endforeach
        </div>
        <a class="studio-add {{ $creatingSlide ? 'is-active' : '' }}" href="{{ $moduleUrl(['step' => 'study', 'new' => 1]) }}">+ Slide</a>
        <a class="studio-questions" href="{{ $moduleUrl(['step' => 'questions']) }}">Questions</a>
    </aside>

    <form id="studio-form" class="studio-workspace" method="post" enctype="multipart/form-data" action="{{ $formAction }}">
        @csrf
        <div class="studio-top">
            <div class="studio-crumb">
                @if ($creatingSlide)
                    <span>New slide</span>
                @else
                    <span>Slide {{ $slideNumber }}</span>
                @endif
            </div>
            <button type="submit" class="studio-save">Save</button>
        </div>

        <div class="studio-tools">
            <button type="button" class="studio-tool" data-add="text">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h10M4 18h14"/></svg>
                Text
            </button>
            <button type="button" class="studio-tool" data-add="list">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 6h11M9 12h11M9 18h11"/><path stroke-linecap="round" d="M4 6h.01M4 12h.01M4 18h.01"/></svg>
                    List
            </button>
            <button type="button" class="studio-tool" data-add="photos">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" d="M3 16l5-4 4 3 3-2 6 5"/></svg>
                Photos
            </button>
            <button type="button" class="studio-tool" data-add="pdf">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/><path stroke-linecap="round" d="M14 3v6h6"/></svg>
                PDF
            </button>
            <button type="button" class="studio-tool" data-add="video">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="14" height="12" rx="2"/><path stroke-linecap="round" d="M17 10l4-2v8l-4-2"/></svg>
                Video
            </button>
            <button type="button" class="studio-tool" data-add="link">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M10 14a5 5 0 007.07 0l1.41-1.41a5 5 0 00-7.07-7.07L10 6"/><path stroke-linecap="round" d="M14 10a5 5 0 00-7.07 0L5.5 11.41a5 5 0 007.07 7.07L14 18"/></svg>
                Link
            </button>
            <button type="button" class="studio-tool" data-add="instruction">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 11l3 3L22 4"/><path stroke-linecap="round" d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                Instructions
            </button>
            <button type="button" class="studio-tool" data-add="note">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 9v4M12 17h.01"/><path stroke-linecap="round" d="M10.3 4.8L2.6 18a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 4.8a2 2 0 00-3.4 0z"/></svg>
                Note
            </button>
            <button type="button" class="studio-tool" data-add="toggle">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
                    Toggle
            </button>
        </div>

        <div class="studio-stage">
            <div class="studio-sheet">
                @include('admin.partials.training-slide-flow', [
                    'flowOwner' => $canvasPage,
                    'isPage' => true,
                    'prefix' => '',
                    'module' => $module,
                    'slidePageId' => $canvasPage->id ?? null,
                ])

                <div class="studio-toggles" data-toggles>
                    @if ($canvasPage)
                        @foreach ($canvasPage->sections as $sIndex => $section)
                            @php
                                $prefix = 'sections['.$section->id.']';
                                $sectionBlocks = $section->blocks ?? collect();
                                $sectionOpen = $openSectionId === (int) $section->id;
                            @endphp
                            <div class="studio-toggle {{ $sectionOpen ? 'is-open' : '' }}" data-toggle>
                                <div class="studio-toggle-bar">
                                    <button type="button" class="studio-toggle-open" data-toggle-open aria-label="Open toggle" aria-expanded="{{ $sectionOpen ? 'true' : 'false' }}">▾</button>
                                    <input class="studio-toggle-title" type="text" name="{{ $prefix }}[title]" maxlength="200" value="{{ old('sections.'.$section->id.'.title', $section->title) }}">
                                    <button type="submit" class="studio-mini-btn" form="sec-up-{{ $section->id }}" aria-label="Move up" @disabled($sIndex === 0)>↑</button>
                                    <button type="submit" class="studio-mini-btn" form="sec-down-{{ $section->id }}" aria-label="Move down" @disabled($sIndex === $canvasPage->sections->count() - 1)>↓</button>
                                    <button type="button" class="studio-mini-btn is-danger" aria-label="Remove toggle"
                                            data-confirm="This toggle will be removed."
                                            data-confirm-title="Remove toggle?"
                                            data-confirm-confirm="Remove"
                                            data-confirm-cancel="Keep"
                                            data-confirm-danger="1"
                                            data-confirm-form="sec-del-{{ $section->id }}">×</button>
                                </div>
                                <div class="studio-toggle-body" data-prefix="{{ $prefix }}">
                                    @include('admin.partials.training-slide-flow', [
                                        'flowOwner' => $section,
                                        'isPage' => false,
                                        'prefix' => $prefix,
                                        'module' => $module,
                                        'slidePageId' => $canvasPage->id,
                                    ])
                                    <div class="studio-toggle-tools">
                                        <button type="button" data-add="text">Text</button>
                                        <button type="button" data-add="photos">Photos</button>
                                        <button type="button" data-add="pdf">PDF</button>
                                        <button type="button" data-add="video">Video</button>
                                        <button type="button" data-add="link">Link</button>
                                        <button type="button" data-add="instruction">Instructions</button>
                                        <button type="button" data-add="note">Note</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </form>
</div>

@if ($canvasPage)
    @foreach ($canvasPage->sections as $section)
        <form id="sec-up-{{ $section->id }}" method="post" action="{{ route('admin.training.sections.move', [$module->id, $canvasPage->id, $section->id]) }}">
            @csrf
            <input type="hidden" name="direction" value="up">
        </form>
        <form id="sec-down-{{ $section->id }}" method="post" action="{{ route('admin.training.sections.move', [$module->id, $canvasPage->id, $section->id]) }}">
            @csrf
            <input type="hidden" name="direction" value="down">
        </form>
        <form id="sec-del-{{ $section->id }}" method="post" action="{{ route('admin.training.sections.destroy', [$module->id, $canvasPage->id, $section->id]) }}">
            @csrf
        </form>
    @endforeach
@endif

<script>
    (function () {
        var form = document.getElementById('studio-form');
        if (!form || form.dataset.studioBound === '1') return;
        form.dataset.studioBound = '1';
        var activeThumb = document.querySelector('.studio-thumb.is-active');
        if (activeThumb) activeThumb.scrollIntoView({ block: 'nearest' });

        function grow(el) {
            if (!el || el.tagName !== 'TEXTAREA') return;
            el.style.height = 'auto';
            el.style.height = Math.max(el.scrollHeight, 72) + 'px';
        }
        form.querySelectorAll('textarea').forEach(function (el) {
            grow(el);
            el.addEventListener('input', function () { grow(el); });
        });

        form.addEventListener('submit', function () {
            var store = form.querySelector('[data-bullet-store]');
            if (store) {
                var lines = [];
                form.querySelectorAll('[data-bullet-line]').forEach(function (input) {
                    var value = input.value.trim();
                    if (value) lines.push(value);
                });
                store.value = lines.join('\n');
            }
            form.querySelectorAll('[data-flow]').forEach(function (flow) {
                var layout = flow.querySelector('[data-layout-store]');
                if (!layout) return;
                var tokens = [];
                Array.prototype.forEach.call(flow.children, function (item) {
                    if (!item.hasAttribute || !item.hasAttribute('data-flow-item')) return;
                    var token = item.getAttribute('data-token');
                    if (token) tokens.push(token);
                });
                layout.value = JSON.stringify(tokens);
            });
        });

        function addBullet() {
            var list = form.querySelector('[data-bullet-list]');
            if (!list) return;
            var row = document.createElement('div');
            row.className = 'studio-bullet';
            row.setAttribute('data-removable', '');
            row.innerHTML = '<span>•</span><input type="text" data-bullet-line maxlength="300"><button type="button" class="studio-x" data-remove-row aria-label="Remove" style="position:static;box-shadow:none;">×</button>';
            list.appendChild(row);
            row.querySelector('input').focus();
        }

        function named(prefix, key) {
            return prefix ? prefix + '[' + key + '][]' : key + '[]';
        }

        function placeInFlow(flow, row) {
            if (!flow) return;
            var store = flow.querySelector('[data-layout-store]');
            if (store) flow.insertBefore(row, store);
            else flow.appendChild(row);
        }

        function addBlock(kind, flow, prefix) {
            prefix = prefix || '';
            if (!flow) return;
            var stamp = Math.random().toString(36).slice(2, 10);
            var row = document.createElement('div');
            row.setAttribute('data-flow-item', '');
            row.setAttribute('data-token', 'new:' + stamp);
            row.setAttribute('data-removable', '');
            var fileAccept = kind === 'pdf'
                ? 'application/pdf,.pdf'
                : 'video/mp4,video/webm,video/quicktime,.mp4,.mov,.webm';
            var file = '<input type="file" name="' + named(prefix, 'new_file') + '" class="studio-pick" accept="' + fileAccept + '"' + (kind === 'pdf' || kind === 'video' ? '' : ' hidden') + '>';
            var label = '<input class="studio-inline" type="text" name="' + named(prefix, 'new_label') + '" maxlength="200"' + (kind === 'text' || kind === 'link' ? ' hidden' : '') + '>';
            var body = kind === 'link' || kind === 'video'
                ? '<input class="studio-inline" type="text" inputmode="url" name="' + named(prefix, 'new_body') + '">'
                : '<textarea class="' + (kind === 'text' ? 'studio-body' : 'studio-inline') + '" name="' + named(prefix, 'new_body') + '" rows="2"' + (kind === 'pdf' ? ' hidden' : '') + '></textarea>';
            var kicker = kind === 'instruction' ? 'Instructions' : (kind === 'note' ? 'Important note' : (kind === 'pdf' ? 'PDF' : (kind === 'video' ? 'Video' : (kind === 'link' ? 'Link' : ''))));
            var close = '<button type="button" class="studio-x" data-remove-row aria-label="Remove">×</button>';
            var fields = (kicker ? '<p class="studio-kicker">' + kicker + '</p>' : '') + label + body + file;
            if (kind === 'link' || kind === 'pdf' || kind === 'video') {
                fields = '<div style="flex:1;min-width:0;">' + fields + '</div>';
            }
            var inner = '<input type="hidden" name="' + named(prefix, 'new_token') + '" value="' + stamp + '">'
                + '<input type="hidden" name="' + named(prefix, 'new_kind') + '" value="' + kind + '">'
                + close + fields;
            if (kind === 'instruction' || kind === 'note') {
                row.className = 'studio-flow-item studio-block studio-callout ' + (kind === 'note' ? 'studio-callout--note' : 'studio-callout--instruction');
            } else if (kind === 'text') {
                row.className = 'studio-flow-item studio-block';
            } else {
                row.className = 'studio-flow-item studio-chip';
            }
            row.innerHTML = inner;
            placeInFlow(flow, row);
            row.scrollIntoView({ block: 'nearest' });
            var focus = kind === 'link'
                ? row.querySelector('input[name*="new_body"]')
                : row.querySelector('textarea, input[type="text"]:not([hidden]), input[type="file"]');
            if (focus) focus.focus();
            grow(row.querySelector('textarea'));
            var area = row.querySelector('textarea');
            if (area) area.addEventListener('input', function () { grow(area); });
            if (kind === 'pdf' || kind === 'video') {
                var picker = row.querySelector('input[type="file"]');
                if (picker) {
                    picker.addEventListener('change', function () {
                        var chosen = picker.files && picker.files[0] ? picker.files[0].name : '';
                        var note = row.querySelector('[data-file-name]');
                        if (!note) {
                            note = document.createElement('p');
                            note.className = 'studio-kicker';
                            note.setAttribute('data-file-name', '');
                            row.appendChild(note);
                        }
                        note.textContent = chosen;
                    });
                    picker.click();
                }
            }
        }

        var newToggleIndex = 0;
        function addToggle() {
            var host = form.querySelector('[data-toggles]');
            if (!host) return;
            var prefix = 'new_sections[' + (newToggleIndex++) + ']';
            var card = document.createElement('div');
            card.className = 'studio-toggle is-open';
            card.setAttribute('data-toggle', '');
            card.setAttribute('data-removable', '');
            card.innerHTML = ''
                + '<div class="studio-toggle-bar">'
                + '<button type="button" class="studio-toggle-open" data-toggle-open aria-label="Open toggle" aria-expanded="true">▾</button>'
                + '<input class="studio-toggle-title" type="text" name="' + prefix + '[title]" maxlength="200" placeholder="Title">'
                + '<button type="button" class="studio-mini-btn is-danger" data-remove-row aria-label="Remove">×</button>'
                + '</div>'
                + '<div class="studio-toggle-body" data-prefix="' + prefix + '">'
                + '<div data-flow>'
                + '<div class="studio-flow-item" data-flow-item data-token="body">'
                + '<textarea class="studio-body" name="' + prefix + '[body]" rows="2" placeholder="Text"></textarea>'
                + '</div>'
                + '<input type="hidden" data-layout-store name="' + prefix + '[content_order]" value="">'
                + '</div>'
                + '<div class="studio-toggle-tools">'
                + '<button type="button" data-add="text">Text</button>'
                + '<button type="button" data-add="photos">Photos</button>'
                + '<button type="button" data-add="pdf">PDF</button>'
                + '<button type="button" data-add="video">Video</button>'
                + '<button type="button" data-add="link">Link</button>'
                + '<button type="button" data-add="instruction">Instructions</button>'
                + '<button type="button" data-add="note">Note</button>'
                + '</div></div>';
            host.appendChild(card);
            var title = card.querySelector('input');
            card.scrollIntoView({ block: 'nearest' });
            if (title) title.focus();
            grow(card.querySelector('textarea'));
        }

        function targetFlow(button) {
            var scope = button.closest('[data-prefix]');
            if (scope) {
                var nested = scope.querySelector('[data-flow]');
                if (nested) return { flow: nested, prefix: scope.getAttribute('data-prefix') || '' };
            }
            return { flow: form.querySelector('[data-flow]'), prefix: '' };
        }

        function addPhotoFiles(files, flow, prefix) {
            if (!files || !flow) return;
            var chosen = Array.prototype.slice.call(files, 0, 5);
            if (files.length > 5) {
                var probe = document.createElement('input');
                probe.setCustomValidity('You can upload up to 5 photos at a time.');
                form.appendChild(probe);
                probe.reportValidity();
                probe.remove();
            }
            chosen.forEach(function (file) {
                if (!file || (file.type && file.type.indexOf('image/') !== 0)) return;
                var stamp = Math.random().toString(36).slice(2, 10);
                var row = document.createElement('div');
                row.className = 'studio-flow-item';
                row.setAttribute('data-flow-item', '');
                row.setAttribute('data-token', 'new:' + stamp);
                row.setAttribute('data-removable', '');
                row.innerHTML = ''
                    + '<div class="studio-photo"><img alt="">'
                    + '<div class="studio-item-tools">'
                    + '<button type="button" class="studio-mini-btn" data-move="up" aria-label="Move up">↑</button>'
                    + '<button type="button" class="studio-mini-btn" data-move="down" aria-label="Move down">↓</button>'
                    + '<button type="button" class="studio-x" data-remove-row aria-label="Remove picture">×</button>'
                    + '</div></div>'
                    + '<input type="hidden" name="' + named(prefix, 'new_kind') + '" value="photo">'
                    + '<input type="hidden" name="' + named(prefix, 'new_token') + '" value="' + stamp + '">'
                    + '<input type="hidden" name="' + named(prefix, 'new_label') + '">'
                    + '<input type="hidden" name="' + named(prefix, 'new_body') + '">'
                    + '<input type="file" name="' + named(prefix, 'new_file') + '" class="studio-file-keep" accept="image/jpeg,image/png,image/webp,image/gif">';
                var keep = row.querySelector('input[type="file"]');
                try {
                    var transfer = new DataTransfer();
                    transfer.items.add(file);
                    keep.files = transfer.files;
                } catch (err) {
                    return;
                }
                row.querySelector('img').src = URL.createObjectURL(file);
                placeInFlow(flow, row);
                row.scrollIntoView({ block: 'nearest' });
            });
        }

        form.addEventListener('click', function (event) {
            var mover = event.target.closest('[data-move]');
            if (mover && form.contains(mover)) {
                var item = mover.closest('[data-flow-item]');
                var flow = item && item.parentElement;
                if (!item || !flow) return;
                var upward = mover.getAttribute('data-move') === 'up';
                var sibling = upward ? item.previousElementSibling : item.nextElementSibling;
                while (sibling && !(sibling.hasAttribute && sibling.hasAttribute('data-flow-item'))) {
                    sibling = upward ? sibling.previousElementSibling : sibling.nextElementSibling;
                }
                if (!sibling) return;
                if (upward) flow.insertBefore(item, sibling);
                else flow.insertBefore(sibling, item);
                return;
            }
            var opener = event.target.closest('[data-toggle-open]');
            if (opener && form.contains(opener)) {
                var card = opener.closest('[data-toggle]');
                if (!card) return;
                var open = !card.classList.contains('is-open');
                card.classList.toggle('is-open', open);
                opener.setAttribute('aria-expanded', open ? 'true' : 'false');
                return;
            }
            var button = event.target.closest('[data-add]');
            if (!button || !form.contains(button)) return;
            var kind = button.getAttribute('data-add');
            if (kind === 'list') {
                addBullet();
                return;
            }
            if (kind === 'toggle') {
                addToggle();
                return;
            }
            var target = targetFlow(button);
            if (kind === 'photos') {
                var picker = document.createElement('input');
                picker.type = 'file';
                picker.multiple = true;
                picker.accept = 'image/jpeg,image/png,image/webp,image/gif';
                picker.addEventListener('change', function () {
                    addPhotoFiles(picker.files, target.flow, target.prefix);
                });
                picker.click();
                return;
            }
            addBlock(kind, target.flow, target.prefix);
        });

        form.addEventListener('focusin', function (event) {
            if (event.target.closest('[data-toggle-open]')) return;
            var card = event.target.closest('[data-toggle]');
            if (!card || !form.contains(card)) return;
            card.classList.add('is-open');
            var opener = card.querySelector('[data-toggle-open]');
            if (opener) opener.setAttribute('aria-expanded', 'true');
        });

        var opened = form.querySelector('.studio-toggle.is-open');
        if (opened) opened.scrollIntoView({ block: 'nearest' });

        document.addEventListener('click', function (event) {
            var discard = event.target.closest('[data-remove-row]');
            if (discard && form.contains(discard)) {
                var row = discard.closest('[data-removable]');
                if (row) row.remove();
                return;
            }
            var button = event.target.closest('[data-remove-name]');
            if (!button || !form.contains(button) || button.dataset.removing === '1') return;
            button.dataset.removing = '1';
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = button.getAttribute('data-remove-name');
            input.value = button.getAttribute('data-remove-value') || '1';
            form.appendChild(input);
            form.noValidate = true;
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        });
    })();
</script>

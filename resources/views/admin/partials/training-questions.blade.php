@php
    $editing = $editingQuestion ?? null;
    $selectedType = old('question_type', $editing->question_type ?? 'single_choice');
    if ($selectedType === 'multiple_choice') {
        $selectedType = 'single_choice';
    }
    $scenarioWritten = old('scenario_mode', ($editing && $editing->question_type === 'scenario' && $editing->options->isEmpty()) ? 'written' : 'choices') === 'written';
    $selection = old('selection', ($editing && $editing->options->where('is_correct', true)->count() > 1) ? 'multiple' : 'single');
    $correctValue = old('correct_value');
    if ($correctValue === null && $editing && in_array($editing->question_type, ['true_false', 'yes_no'], true)) {
        $marked = $editing->options->firstWhere('is_correct', true);
        $correctValue = $editing->question_type === 'true_false'
            ? (strtolower((string) ($marked->option_text ?? 'True')) === 'false' ? 'false' : 'true')
            : (strtolower((string) ($marked->option_text ?? 'Yes')) === 'no' ? 'no' : 'yes');
    }
    $correctValue = $correctValue ?: ($selectedType === 'yes_no' ? 'yes' : 'true');
    $acceptedText = old('accepted_answers', $editing ? implode("\n", $editing->accepted_answers ?? []) : '');
    $formAction = $editing
        ? route('admin.training.questions.update', [$module->id, $editing->id])
        : route('admin.training.questions.store', $module->id);
    $choiceTypes = ['multiple_choice', 'single_choice', 'multiple_answer', 'scenario', 'image', 'video'];
    $optionModels = ($editing && in_array($editing->question_type, $choiceTypes, true)) ? $editing->options->values() : collect();
    $defaultCorrect = 0;
    $savedCorrectIndexes = [];
    foreach ($optionModels as $idx => $opt) {
        if ($opt->is_correct) {
            $savedCorrectIndexes[] = (int) $idx;
            if ($defaultCorrect === 0 && $idx !== 0 && ! in_array(0, $savedCorrectIndexes, true)) {
                $defaultCorrect = (int) $idx;
            }
        }
    }
    if ($savedCorrectIndexes !== [] && ! in_array($defaultCorrect, $savedCorrectIndexes, true)) {
        $defaultCorrect = $savedCorrectIndexes[0];
    }
    $correctIndex = (int) old('correct_index', $defaultCorrect);
    $postedCorrectIndexes = array_map('intval', (array) old('correct_indexes', $savedCorrectIndexes));
    $markMultiple = $selectedType === 'multiple_answer'
        || (in_array($selectedType, ['image', 'video', 'scenario'], true) && $selection === 'multiple' && ! ($selectedType === 'scenario' && $scenarioWritten));
    $shows = function (string $spec) use ($selectedType, $scenarioWritten): bool {
        $allowed = preg_split('/\s+/', trim($spec)) ?: [];
        $types = [$selectedType];
        if ($selectedType === 'scenario' && $scenarioWritten) {
            $types[] = 'scenario-written';
        }
        if (in_array('scenario', $types, true) && $scenarioWritten && in_array('scenario', $allowed, true) && in_array('short_answer', $allowed, true)) {
            return true;
        }
        if (in_array('scenario', $types, true) && $scenarioWritten && (in_array('multiple_choice', $allowed, true) || in_array('image', $allowed, true))) {
            return false;
        }
        if (in_array('short_answer', $allowed, true) && $selectedType === 'scenario' && ! $scenarioWritten) {
            return false;
        }

        return count(array_intersect($allowed, $types)) > 0;
    };
    $typeGroups = [
        'They pick one' => [
            'single_choice' => 'A few options. One is right.',
            'true_false' => 'The statement is true or false.',
            'yes_no' => 'The answer is yes or no.',
        ],
        'They pick more than one' => [
            'multiple_answer' => 'Tick every option that is right.',
        ],
        'They type it' => [
            'short_answer' => 'A short written reply.',
            'fill_blank' => 'One missing word or phrase.',
        ],
        'They sort it' => [
            'matching' => 'Pair each term with its match.',
            'ordering' => 'Put the steps in order.',
        ],
        'A situation or a file' => [
            'scenario' => 'A short situation, then a question.',
            'image' => 'A picture, then the options.',
            'video' => 'A short clip, then the options.',
        ],
    ];
    $questionCount = $module->questions->count();
    $selectedGroup = 'They pick one';
    $selectedHint = 'A few options. One is right.';
    foreach ($typeGroups as $group => $types) {
        if (isset($types[$selectedType])) {
            $selectedGroup = $group;
            $selectedHint = $types[$selectedType];
            break;
        }
    }
@endphp

<section class="mb-6 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b border-brand-border px-5 py-5 sm:px-6">
        <div>
            <h2 class="text-lg font-bold text-brand-text">Quiz rules</h2>
            <p class="mt-1 max-w-xl text-sm text-brand-text/60">Set the pass mark and how many tries employees get. You can change this any time.</p>
        </div>
        <p class="text-sm font-semibold text-brand-primary">{{ $questionCount }} {{ $questionCount === 1 ? 'question' : 'questions' }}</p>
    </div>
    <form method="post" action="{{ route('admin.training.quiz.update', $module->id) }}" class="space-y-4 px-5 py-5 sm:px-6">
        @csrf
        <input type="hidden" name="quiz_required" value="1">
        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Pass mark</span>
                <span class="flex items-center gap-2">
                    <input type="number" name="pass_percent" min="1" max="100" required value="{{ old('pass_percent', $module->pass_percent ?? 70) }}" class="{{ $in }}">
                    <span class="text-sm font-semibold text-brand-text/50">%</span>
                </span>
                <span class="mt-1.5 block text-xs text-brand-text/50">Most quizzes use 70.</span>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Time per question</span>
                <span class="flex items-center gap-2">
                    <input type="number" name="question_time_seconds" min="10" max="600" required value="{{ old('question_time_seconds', $module->question_time_seconds ?? 45) }}" class="{{ $in }}">
                    <span class="text-sm font-semibold text-brand-text/50">sec</span>
                </span>
                <span class="mt-1.5 block text-xs text-brand-text/50">How long they have to answer.</span>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Tries allowed</span>
                <input type="number" name="max_attempts" min="1" max="10" required value="{{ old('max_attempts', $module->max_attempts ?? ($induction ? 3 : 1)) }}" class="{{ $in }}">
                <span class="mt-1.5 block text-xs text-brand-text/50">1 is a single try. More than 1 lets them retake until that number is used.</span>
            </label>
        </div>
        @if ($induction)
            <p class="rounded-xl border border-brand-primary/20 bg-brand-primary/5 px-4 py-3 text-sm text-brand-text/80">Induction quizzes are required. Employees can try again until they pass or use every attempt.</p>
        @endif
        <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">Save rules</button>
    </form>
</section>

<section id="quiz-builder" class="mb-6 rounded-2xl border border-brand-border bg-white shadow-sm">
    <div class="border-b border-brand-border px-5 py-5 sm:px-6">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">{{ $editing ? 'Editing a question' : 'New question' }}</p>
        <h2 class="mt-1 text-lg font-bold text-brand-text">{{ $editing ? 'Update this question' : 'Add a question' }}</h2>
        <p class="mt-1 text-sm text-brand-text/60">Pick a type, write the question, then mark the right answer.</p>
    </div>
    <form id="quiz-question-form" method="post" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-6 px-5 py-5 sm:px-6">
        @csrf
        <div id="quiz-type-menu" class="relative">
            <p class="mb-2 text-sm font-semibold text-brand-text">1. What kind of question is this?</p>
            <button type="button" id="quiz-type-toggle" aria-expanded="false" aria-controls="quiz-type-list" class="flex w-full items-center justify-between gap-3 rounded-xl border border-brand-border bg-white px-4 py-3 text-left shadow-sm transition hover:border-brand-primary/50 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                <span class="min-w-0">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-brand-label" data-type-current-group>{{ $selectedGroup }}</span>
                    <span class="mt-0.5 block truncate text-sm font-semibold text-brand-text" data-type-current>{{ \App\Support\TrainingQuiz::label($selectedType) }}</span>
                    <span class="block truncate text-xs text-brand-text/55" data-type-current-hint>{{ $selectedHint }}</span>
                </span>
                <svg class="size-5 shrink-0 text-brand-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
            <div id="quiz-type-list" hidden class="absolute left-0 right-0 top-full z-30 mt-2 max-h-72 overflow-y-auto rounded-2xl border border-brand-border bg-white p-2 shadow-lg ring-1 ring-black/[0.06]">
                @foreach ($typeGroups as $group => $types)
                    <p class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-brand-label">{{ $group }}</p>
                    @foreach ($types as $value => $hint)
                        <button type="button"
                                data-pick-type="{{ $value }}"
                                data-type-group="{{ $group }}"
                                data-type-hint="{{ $hint }}"
                                role="option"
                                aria-selected="{{ $selectedType === $value ? 'true' : 'false' }}"
                                class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left hover:bg-brand-surface {{ $selectedType === $value ? 'bg-brand-primary/5' : '' }}">
                            <span class="min-w-0">
                                <span data-type-name class="block text-sm font-semibold text-brand-text">{{ \App\Support\TrainingQuiz::label($value) }}</span>
                                <span class="block text-xs text-brand-text/55">{{ $hint }}</span>
                            </span>
                            <span data-type-check class="text-sm font-bold text-brand-primary {{ $selectedType === $value ? '' : 'hidden' }}" aria-hidden="true">✓</span>
                        </button>
                    @endforeach
                @endforeach
            </div>
            <label class="sr-only">
                Question type
                <select name="question_type" tabindex="-1">
                    @foreach ($questionTypes as $value => $label)
                        @continue($value === 'multiple_choice')
                        <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <p id="quiz-type-help" class="mt-3 rounded-xl bg-brand-surface px-4 py-3 text-sm text-brand-text/70"></p>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <label class="block sm:col-span-3">
                <span id="quiz-question-label" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">2. Question</span>
                <textarea name="question_text" rows="3" required class="{{ $in }}" placeholder="Write the question the way the employee will read it.">{{ old('question_text', $editing->question_text ?? '') }}</textarea>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Marks</span>
                <input type="number" name="points" min="1" max="100" value="{{ old('points', $editing->points ?? 1) }}" class="{{ $in }}">
            </label>
        </div>

        <div data-for="scenario" @unless($shows('scenario')) hidden @endunless>
            <p class="mb-2 text-sm font-semibold text-brand-text">Describe the situation</p>
            <textarea name="prompt" rows="3" class="{{ $in }}" data-keep-required="1" placeholder="What happened, and what does the employee need to decide?">{{ old('prompt', $editing->prompt ?? '') }}</textarea>
            <input type="checkbox" name="scenario_mode" value="written" class="sr-only" tabindex="-1" aria-hidden="true" @checked($scenarioWritten) data-scenario-written>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <button type="button" data-scenario-choice="choices" class="rounded-xl border px-3 py-3 text-left {{ $scenarioWritten ? 'border-brand-border bg-white' : 'border-brand-primary bg-brand-primary/5' }}">
                    <span class="block text-sm font-semibold text-brand-text">They pick an option</span>
                    <span class="mt-0.5 block text-xs text-brand-text/55">You mark which option is right.</span>
                </button>
                <button type="button" data-scenario-choice="written" class="rounded-xl border px-3 py-3 text-left {{ $scenarioWritten ? 'border-brand-primary bg-brand-primary/5' : 'border-brand-border bg-white' }}">
                    <span class="block text-sm font-semibold text-brand-text">They write an answer</span>
                    <span class="mt-0.5 block text-xs text-brand-text/55">You can mark replies that do not match exactly.</span>
                </button>
            </div>
        </div>

        <div data-for="image video" @unless($shows('image video')) hidden @endunless>
            <p class="mb-2 text-sm font-semibold text-brand-text" data-media-title>{{ $selectedType === 'video' ? 'Add the video' : 'Add the picture' }}</p>
            <input type="hidden" name="remove_media" value="0">
            @if ($editing && $editing->media_path)
                @php $mediaUrl = route('admin.training.questions.media', [$module->id, $editing->id]).'?v='.\App\Support\TrainingSlideMedia::version($editing->media_path); @endphp
                <div data-media-current class="relative mb-3 overflow-hidden rounded-2xl border border-brand-border bg-brand-surface/40 p-3">
                    <div class="relative mx-auto w-fit max-w-full" style="position:relative;width:fit-content;max-width:100%;margin:0 auto">
                        @if ($editing->media_kind === 'video')
                            <video src="{{ $mediaUrl }}" controls class="max-h-72 w-full rounded-xl bg-black" style="max-height:18rem;width:100%;background:#000"></video>
                        @else
                            <img src="{{ $mediaUrl }}" alt="" class="max-h-72 w-full rounded-xl object-contain" style="max-height:18rem;width:auto;max-width:100%;object-fit:contain">
                        @endif
                        <button type="button" data-clear-media aria-label="Remove this file" style="position:absolute;top:0.5rem;right:0.5rem;z-index:10;display:flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:9999px;background:#fff;color:#0f172a;box-shadow:0 1px 4px rgba(15,23,42,.28);border:0;padding:0;cursor:pointer">
                            <svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
            @endif
            <div data-media-live class="mb-3" hidden></div>
            <label class="block rounded-2xl border border-dashed border-brand-border bg-brand-surface/40 px-4 py-4">
                <span class="mb-2 block text-xs text-brand-text/60" data-media-note>{{ $editing && $editing->media_path ? 'Choose a different file to replace it.' : ($selectedType === 'video' ? 'MP4, MOV, or WebM.' : 'JPG, PNG, or WebP.') }}</span>
                <input type="file" name="media" class="{{ $in }}" accept="image/*,video/mp4,video/webm,video/quicktime,.jfif">
                @error('media') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </label>
        </div>

        <div data-for="true_false yes_no" @unless($shows('true_false yes_no')) hidden @endunless>
            <p class="mb-2 text-sm font-semibold text-brand-text">3. Which answer is right?</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <label data-for="true_false" @unless($shows('true_false')) hidden @endunless class="cursor-pointer">
                    <input type="radio" name="correct_value" value="true" @checked($correctValue === 'true') class="peer sr-only">
                    <span class="block rounded-2xl border border-brand-border px-4 py-4 text-center text-sm font-semibold peer-checked:border-brand-primary peer-checked:bg-brand-primary peer-checked:text-white">True</span>
                </label>
                <label data-for="true_false" @unless($shows('true_false')) hidden @endunless class="cursor-pointer">
                    <input type="radio" name="correct_value" value="false" @checked($correctValue === 'false') class="peer sr-only">
                    <span class="block rounded-2xl border border-brand-border px-4 py-4 text-center text-sm font-semibold peer-checked:border-brand-primary peer-checked:bg-brand-primary peer-checked:text-white">False</span>
                </label>
                <label data-for="yes_no" @unless($shows('yes_no')) hidden @endunless class="cursor-pointer">
                    <input type="radio" name="correct_value" value="yes" @checked($correctValue === 'yes') class="peer sr-only">
                    <span class="block rounded-2xl border border-brand-border px-4 py-4 text-center text-sm font-semibold peer-checked:border-brand-primary peer-checked:bg-brand-primary peer-checked:text-white">Yes</span>
                </label>
                <label data-for="yes_no" @unless($shows('yes_no')) hidden @endunless class="cursor-pointer">
                    <input type="radio" name="correct_value" value="no" @checked($correctValue === 'no') class="peer sr-only">
                    <span class="block rounded-2xl border border-brand-border px-4 py-4 text-center text-sm font-semibold peer-checked:border-brand-primary peer-checked:bg-brand-primary peer-checked:text-white">No</span>
                </label>
            </div>
        </div>

        <div data-for="multiple_choice single_choice multiple_answer scenario image video" @unless($shows('multiple_choice single_choice multiple_answer scenario image video')) hidden @endunless>
            <div data-for="image video scenario" class="mb-4" @unless($shows('image video scenario')) hidden @endunless>
                <p class="mb-2 text-sm font-semibold text-brand-text">How do they answer?</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="selection" value="single" @checked($selection !== 'multiple') class="peer sr-only">
                        <span class="block rounded-xl border border-brand-border px-3 py-3 peer-checked:border-brand-primary peer-checked:bg-brand-primary/5">
                            <span class="block text-sm font-semibold text-brand-text">One right answer</span>
                            <span class="mt-0.5 block text-xs text-brand-text/55">Click the circle beside it.</span>
                        </span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="selection" value="multiple" @checked($selection === 'multiple') class="peer sr-only">
                        <span class="block rounded-xl border border-brand-border px-3 py-3 peer-checked:border-brand-primary peer-checked:bg-brand-primary/5">
                            <span class="block text-sm font-semibold text-brand-text">More than one</span>
                            <span class="mt-0.5 block text-xs text-brand-text/55">Tick every right answer.</span>
                        </span>
                    </label>
                </div>
            </div>
            <p class="mb-1 text-sm font-semibold text-brand-text">3. Answers</p>
            <p id="quiz-choice-help" class="mb-3 text-xs text-brand-text/55">{{ $markMultiple ? 'Tick every answer that is right. Leave unused rows empty.' : 'Click the circle next to the one right answer. Leave unused rows empty.' }}</p>
            @for ($i = 0; $i < 6; $i++)
                @php
                    $saved = $optionModels[$i] ?? null;
                    $optionText = old('options.'.$i.'.text', $saved->option_text ?? '');
                    $rowOpen = $i < 3 || trim((string) $optionText) !== '';
                @endphp
                <div class="mb-2 flex items-center gap-2 rounded-xl border border-brand-border bg-white px-3 py-2 {{ $rowOpen ? '' : 'hidden' }}" @unless($rowOpen) data-extra="options" @endunless>
                    <input type="radio" name="correct_index" value="{{ $i }}" @checked($correctIndex === $i) class="size-4 shrink-0 text-brand-primary {{ $markMultiple ? 'hidden' : '' }}" data-single-correct aria-label="Mark answer {{ $i + 1 }} as the right one" @disabled($markMultiple)>
                    <input type="checkbox" name="correct_indexes[]" value="{{ $i }}" @checked(in_array($i, $postedCorrectIndexes, true)) class="size-4 shrink-0 rounded border-brand-border text-brand-primary {{ $markMultiple ? '' : 'hidden' }}" data-multi-correct aria-label="Answer {{ $i + 1 }} is right" @disabled(! $markMultiple)>
                    <input type="text" name="options[{{ $i }}][text]" value="{{ $optionText }}" class="w-full border-0 bg-transparent px-1 py-1.5 text-sm text-brand-text outline-none placeholder:text-brand-text/35" placeholder="Answer {{ $i + 1 }}{{ $i > 1 ? ' (optional)' : '' }}" @if ($i < 2) data-keep-required="1" @endif>
                </div>
            @endfor
            <button type="button" data-add-rows="options" class="text-sm font-semibold text-brand-primary hover:underline">Add another answer</button>
        </div>

        <div data-for="matching" @unless($shows('matching')) hidden @endunless>
            <p class="mb-1 text-sm font-semibold text-brand-text">3. Pairs</p>
            <p class="mb-3 text-xs text-brand-text/55">Write the term on the left and the match on the right. The phone shuffles the matches.</p>
            <div class="mb-2 hidden gap-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-brand-label sm:grid sm:grid-cols-2">
                <span>Term</span>
                <span>Matches</span>
            </div>
            @for ($i = 0; $i < 6; $i++)
                @php
                    $saved = ($editing && $editing->question_type === 'matching') ? ($editing->options->values()[$i] ?? null) : null;
                    $left = old('pairs.'.$i.'.left', $saved->option_text ?? '');
                    $right = old('pairs.'.$i.'.right', $saved->match_text ?? '');
                    $rowOpen = $i < 2 || trim((string) $left) !== '' || trim((string) $right) !== '';
                @endphp
                <div class="mb-2 grid gap-2 sm:grid-cols-2 {{ $rowOpen ? '' : 'hidden' }}" @unless($rowOpen) data-extra="pairs" @endunless>
                    <input type="text" name="pairs[{{ $i }}][left]" value="{{ $left }}" class="{{ $in }}" placeholder="Term {{ $i + 1 }}" @if ($i < 2) data-keep-required="1" @endif>
                    <input type="text" name="pairs[{{ $i }}][right]" value="{{ $right }}" class="{{ $in }}" placeholder="Match {{ $i + 1 }}" @if ($i < 2) data-keep-required="1" @endif>
                </div>
            @endfor
            <button type="button" data-add-rows="pairs" class="text-sm font-semibold text-brand-primary hover:underline">Add another pair</button>
        </div>

        <div data-for="ordering" @unless($shows('ordering')) hidden @endunless>
            <p class="mb-1 text-sm font-semibold text-brand-text">3. Correct order</p>
            <p class="mb-3 text-xs text-brand-text/55">Write the steps from first to last. The phone shows them in a mixed order.</p>
            @for ($i = 0; $i < 6; $i++)
                @php
                    $saved = ($editing && $editing->question_type === 'ordering') ? ($editing->options->values()[$i] ?? null) : null;
                    $itemText = old('items.'.$i, $saved->option_text ?? '');
                    $rowOpen = $i < 3 || trim((string) $itemText) !== '';
                @endphp
                <div class="mb-2 flex items-center gap-2 {{ $rowOpen ? '' : 'hidden' }}" @unless($rowOpen) data-extra="items" @endunless>
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-bold text-brand-primary">{{ $i + 1 }}</span>
                    <input type="text" name="items[{{ $i }}]" value="{{ $itemText }}" class="{{ $in }}" placeholder="Step {{ $i + 1 }}{{ $i > 1 ? ' (optional)' : '' }}" @if ($i < 2) data-keep-required="1" @endif>
                </div>
            @endfor
            <button type="button" data-add-rows="items" class="text-sm font-semibold text-brand-primary hover:underline">Add another step</button>
        </div>

        <div data-for="short_answer fill_blank scenario" @unless($shows('short_answer fill_blank scenario')) hidden @endunless>
            <p class="mb-1 text-sm font-semibold text-brand-text">3. Answers you will accept</p>
            <p class="mb-3 text-xs text-brand-text/55" data-accepted-help>Put each accepted answer on its own line. An exact match is marked correct. Anything else can wait for you.</p>
            <textarea name="accepted_answers" rows="4" class="{{ $in }}" placeholder="wet floor sign&#10;warning sign">{{ $acceptedText }}</textarea>
            <input type="hidden" name="requires_review" value="0">
            <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border border-brand-border px-3 py-3">
                <input type="checkbox" name="requires_review" value="1" class="mt-0.5 size-4 rounded border-brand-border text-brand-primary" @checked((bool) old('requires_review', $editing->requires_review ?? in_array($selectedType, ['short_answer', 'scenario'], true)))>
                <span>
                    <span class="block text-sm font-semibold text-brand-text">Hold other replies for you to mark</span>
                    <span class="mt-0.5 block text-xs text-brand-text/55">If their wording does not match the list, the quiz waits until you mark it right or wrong.</span>
                </span>
            </label>
        </div>

        <details class="rounded-xl border border-brand-border bg-brand-surface/30 px-4 py-3">
            <summary class="cursor-pointer text-sm font-semibold text-brand-text">Feedback after they submit (optional)</summary>
            <label class="mt-3 block">
                <span class="mb-1.5 block text-xs text-brand-text/60">Shown on the phone after the quiz, with the right answer.</span>
                <textarea name="explanation" rows="2" class="{{ $in }}" placeholder="A short note about why this answer is right.">{{ old('explanation', $editing->explanation ?? '') }}</textarea>
            </label>
        </details>

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">{{ $editing ? 'Save question' : 'Add question' }}</button>
            @if ($editing)
                <a href="{{ $moduleUrl(['step' => 'questions']) }}" class="text-sm font-semibold text-brand-text/60 hover:underline">Cancel</a>
            @endif
        </div>
    </form>
</section>

<section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-5 py-5 sm:px-6">
        <div>
            <h2 class="text-lg font-bold text-brand-text">Questions in this quiz</h2>
            <p class="mt-1 text-sm text-brand-text/60">Employees see these in order on the phone.</p>
        </div>
        <a href="{{ $moduleUrl(['step' => 'assign']) }}" class="inline-flex rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">
            {{ $induction ? 'Continue to eligibility' : 'Continue to assign' }}
        </a>
    </div>
    <div class="space-y-3 px-5 py-5 sm:px-6">
        @forelse ($module->questions as $index => $question)
            @php
                $type = \App\Support\TrainingQuiz::typeOf($question);
                $answer = \App\Support\TrainingQuiz::preview($question);
                $isEditing = $editing && (int) $editing->id === (int) $question->id;
            @endphp
            <article class="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-start {{ $isEditing ? 'border-brand-primary bg-brand-primary/5 ring-2 ring-brand-primary/20' : 'border-brand-border bg-white' }}">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-sm font-bold text-brand-primary">{{ $index + 1 }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-brand-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-brand-primary">{{ \App\Support\TrainingQuiz::label($type) }}</span>
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-brand-text/45">{{ $question->points }} {{ (int) $question->points === 1 ? 'mark' : 'marks' }}</span>
                        @if ($question->requires_review)
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">You mark replies</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm font-semibold text-brand-text">{{ $question->question_text }}</p>
                    @if ($question->prompt)
                        <p class="mt-1 text-xs leading-5 text-brand-text/60">{{ $question->prompt }}</p>
                    @endif
                    @if ($answer !== '')
                        <p class="mt-2 text-sm text-emerald-700">Right answer: {{ $answer }}</p>
                    @endif
                    @if ($question->explanation)
                        <p class="mt-1 text-xs text-brand-text/55">Feedback: {{ $question->explanation }}</p>
                    @endif
                    @if ($question->media_path)
                        @php $cardMedia = route('admin.training.questions.media', [$module->id, $question->id]).'?v='.\App\Support\TrainingSlideMedia::version($question->media_path); @endphp
                        @if ($question->media_kind === 'video')
                            <video src="{{ $cardMedia }}" controls class="mt-3 max-h-72 w-full rounded-xl bg-black" style="max-height:18rem;width:100%;background:#000"></video>
                        @else
                            <img src="{{ $cardMedia }}" alt="" class="mt-3 max-h-72 w-full rounded-xl border border-brand-border object-contain" style="max-height:18rem;width:100%;object-fit:contain">
                        @endif
                    @endif
                </div>
                <div class="flex shrink-0 gap-2 sm:flex-col">
                    <a href="{{ $moduleUrl(['step' => 'questions', 'edit_question' => $question->id]) }}#quiz-builder" class="rounded-lg border border-brand-border px-3 py-1.5 text-center text-xs font-semibold text-brand-primary hover:border-brand-primary/50">Edit</a>
                    <form method="post"
                          action="{{ route('admin.training.questions.destroy', [$module->id, $question->id]) }}"
                          data-confirm="This question will be removed from the quiz."
                          data-confirm-title="Remove question?"
                          data-confirm-confirm="Remove"
                          data-confirm-cancel="Keep question"
                          data-confirm-danger="1">
                        @csrf
                        <button type="submit" class="w-full rounded-lg px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Remove</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-brand-border px-4 py-10 text-center">
                <p class="text-sm font-semibold text-brand-text">No questions yet</p>
                <p class="mt-1 text-sm text-brand-text/60">Use the form above. A required quiz needs at least one question before you can assign it.</p>
            </div>
        @endforelse
    </div>
</section>

<script>
(function () {
    const root = document.getElementById('quiz-question-form');
    if (!root) return;
    const typeInput = root.querySelector('[name="question_type"]');
    const panels = root.querySelectorAll('[data-for]');
    const written = root.querySelector('[data-scenario-written]');
    const help = document.getElementById('quiz-type-help');
    const choiceHelp = document.getElementById('quiz-choice-help');
    const questionLabel = document.getElementById('quiz-question-label');
    const hints = {
        multiple_choice: 'Add the answers below, then click the circle beside the one that is right.',
        single_choice: 'Add the answers below, then click the circle beside the one that is right.',
        multiple_answer: 'Add the answers below, then tick every one that is right.',
        true_false: 'Write a statement, then choose True or False.',
        yes_no: 'Write the question, then choose Yes or No.',
        short_answer: 'They type a short reply. List the wording you will mark correct. Other replies can wait for you.',
        scenario: 'Describe the situation first, then choose whether they pick an option or write an answer.',
        matching: 'Each row is one pair. You only need two pairs to start.',
        ordering: 'The first row is step 1. You only need two steps to start.',
        fill_blank: 'Write the question with a blank, such as “Place a ____ before mopping.” Then list the words you accept.',
        image: 'Upload the picture, add the answers, and mark the right one.',
        video: 'Upload the clip, add the answers, and mark the right one.'
    };

    function activeTypes() {
        const type = typeInput.value;
        const types = [type];
        if (type === 'scenario' && written && written.checked) {
            types.push('scenario-written');
        }
        return types;
    }

    function panelMatches(panel, types) {
        const allowed = (panel.getAttribute('data-for') || '').split(/\s+/);
        if (types.includes('scenario') && written && written.checked && allowed.includes('scenario') && allowed.includes('short_answer')) {
            return true;
        }
        if (types.includes('scenario') && written && written.checked && (allowed.includes('multiple_choice') || allowed.includes('image'))) {
            return false;
        }
        if (allowed.includes('short_answer') && types[0] === 'scenario' && !(written && written.checked)) {
            return false;
        }
        return allowed.some((name) => types.includes(name));
    }

    const typeMenu = document.getElementById('quiz-type-list');
    const typeToggle = document.getElementById('quiz-type-toggle');

    function setTypeMenu(open) {
        if (!typeMenu || !typeToggle) return;
        typeMenu.hidden = !open;
        typeToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function paintType(type) {
        root.querySelectorAll('[data-pick-type]').forEach((button) => {
            const on = button.getAttribute('data-pick-type') === type;
            button.setAttribute('aria-selected', on ? 'true' : 'false');
            button.classList.toggle('bg-brand-primary/5', on);
            const check = button.querySelector('[data-type-check]');
            if (check) check.classList.toggle('hidden', !on);
            if (!on) return;
            const current = root.querySelector('[data-type-current]');
            const hintEl = root.querySelector('[data-type-current-hint]');
            const groupEl = root.querySelector('[data-type-current-group]');
            const name = button.querySelector('[data-type-name]');
            if (current && name) current.textContent = name.textContent;
            if (hintEl) hintEl.textContent = button.getAttribute('data-type-hint') || '';
            if (groupEl) groupEl.textContent = button.getAttribute('data-type-group') || '';
        });
        if (help) {
            help.textContent = hints[type] || hints.multiple_choice;
        }
        if (questionLabel) {
            questionLabel.textContent = type === 'true_false' ? '2. Statement' : (type === 'fill_blank' ? '2. Question, with the blank' : '2. Question');
        }
        const mediaTitle = root.querySelector('[data-media-title]');
        const mediaNote = root.querySelector('[data-media-note]');
        if (mediaTitle) mediaTitle.textContent = type === 'video' ? 'Add the video' : 'Add the picture';
        if (mediaNote) {
            const current = root.querySelector('[data-media-current]');
            const removed = root.querySelector('input[name="remove_media"]')?.value === '1';
            mediaNote.textContent = current && !current.hidden && !removed
                ? 'Choose a different file to replace it.'
                : (type === 'video' ? 'MP4, MOV, or WebM.' : 'JPG, PNG, or WebP.');
        }
        root.querySelectorAll('[data-scenario-choice]').forEach((button) => {
            const on = (button.getAttribute('data-scenario-choice') === 'written') === (written && written.checked);
            button.className = 'rounded-xl border px-3 py-3 text-left ' + (on
                ? 'border-brand-primary bg-brand-primary/5'
                : 'border-brand-border bg-white');
        });
    }

    function sync() {
        const types = activeTypes();
        const multiple = types.includes('multiple_answer') || (['image', 'video', 'scenario'].includes(types[0]) && root.querySelector('[name="selection"]:checked')?.value === 'multiple');
        panels.forEach((panel) => {
            const show = panelMatches(panel, types);
            panel.hidden = !show;
            panel.querySelectorAll('input, textarea, select').forEach((field) => {
                if (field === typeInput) return;
                if (field.hasAttribute('data-scenario-written')) {
                    field.disabled = !show;
                    return;
                }
                if (!show) {
                    if (field.required) field.dataset.wasRequired = '1';
                    field.required = false;
                    field.disabled = true;
                    return;
                }
                field.disabled = false;
                if (field.dataset.wasRequired === '1' || field.dataset.keepRequired === '1') {
                    field.required = true;
                }
                if (field.hasAttribute('data-single-correct')) {
                    field.disabled = multiple;
                    field.classList.toggle('hidden', multiple);
                }
                if (field.hasAttribute('data-multi-correct')) {
                    field.disabled = !multiple;
                    field.classList.toggle('hidden', !multiple);
                }
            });
        });
        if (choiceHelp) {
            choiceHelp.textContent = multiple
                ? 'Tick every answer that is right. Leave unused rows empty.'
                : 'Click the circle next to the one right answer. Leave unused rows empty.';
        }
        const acceptedHelp = root.querySelector('[data-accepted-help]');
        if (acceptedHelp) {
            acceptedHelp.textContent = types[0] === 'fill_blank'
                ? 'Required. Put each accepted word or phrase on its own line.'
                : 'Put each accepted answer on its own line. An exact match is marked correct. Anything else can wait for you.';
        }
        paintType(types[0]);
        root.querySelectorAll('[data-add-rows]').forEach((button) => {
            const group = button.getAttribute('data-add-rows');
            const pending = root.querySelector('[data-extra="' + group + '"]');
            button.hidden = !pending;
        });
    }

    if (typeToggle) {
        typeToggle.addEventListener('click', () => setTypeMenu(typeMenu.hidden));
    }
    root.querySelectorAll('[data-pick-type]').forEach((button) => {
        button.addEventListener('click', () => {
            typeInput.value = button.getAttribute('data-pick-type');
            typeInput.dispatchEvent(new Event('change', { bubbles: true }));
            setTypeMenu(false);
        });
    });
    document.addEventListener('click', (event) => {
        const menu = document.getElementById('quiz-type-menu');
        if (menu && !menu.contains(event.target)) setTypeMenu(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setTypeMenu(false);
    });

    root.querySelectorAll('[data-scenario-choice]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!written) return;
            written.checked = button.getAttribute('data-scenario-choice') === 'written';
            written.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    const mediaInput = root.querySelector('input[type="file"][name="media"]');
    const mediaLive = root.querySelector('[data-media-live]');
    const mediaCurrent = root.querySelector('[data-media-current]');
    const removeMedia = root.querySelector('input[name="remove_media"]');
    const clearSavedMedia = () => {
        if (removeMedia) removeMedia.value = '1';
        if (mediaCurrent) {
            mediaCurrent.dataset.dismissed = '1';
            mediaCurrent.hidden = true;
        }
        if (mediaInput) mediaInput.value = '';
        if (mediaLive) {
            mediaLive.replaceChildren();
            mediaLive.hidden = true;
        }
        sync();
    };
    const clearChosenMedia = () => {
        if (mediaInput) mediaInput.value = '';
        if (mediaLive) {
            mediaLive.replaceChildren();
            mediaLive.hidden = true;
        }
        if (mediaCurrent && mediaCurrent.dataset.dismissed !== '1') {
            mediaCurrent.hidden = false;
            if (removeMedia) removeMedia.value = '0';
        }
        sync();
    };
    root.addEventListener('submit', () => {
        const shown = activeTypes()[0] === 'image' || activeTypes()[0] === 'video';
        if (mediaInput) mediaInput.disabled = !shown;
        if (removeMedia) removeMedia.disabled = !shown;
    });
    root.querySelector('[data-clear-media]')?.addEventListener('click', clearSavedMedia);
    mediaInput?.addEventListener('change', () => {
        const file = mediaInput.files && mediaInput.files[0];
        if (!file || !mediaLive) return;
        if (removeMedia) removeMedia.value = '0';
        if (mediaCurrent) mediaCurrent.hidden = true;
        const url = URL.createObjectURL(file);
        const frame = document.createElement('div');
        frame.className = 'overflow-hidden rounded-2xl border border-brand-border bg-brand-surface/40 p-3';
        const stage = document.createElement('div');
        stage.style.cssText = 'position:relative;width:fit-content;max-width:100%;margin:0 auto';
        const close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Remove this file');
        close.style.cssText = 'position:absolute;top:0.5rem;right:0.5rem;z-index:10;display:flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:9999px;background:#fff;color:#0f172a;box-shadow:0 1px 4px rgba(15,23,42,.28);border:0;padding:0;cursor:pointer';
        close.innerHTML = '<svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/></svg>';
        close.addEventListener('click', clearChosenMedia);
        let node;
        if (file.type.indexOf('video') === 0) {
            node = document.createElement('video');
            node.src = url;
            node.controls = true;
            node.className = 'max-h-72 w-full rounded-xl bg-black';
            node.style.maxHeight = '18rem';
            node.style.width = '100%';
            node.style.background = '#000';
        } else {
            node = document.createElement('img');
            node.src = url;
            node.alt = '';
            node.className = 'max-h-72 rounded-xl object-contain';
            node.style.maxHeight = '18rem';
            node.style.width = 'auto';
            node.style.maxWidth = '100%';
            node.style.objectFit = 'contain';
        }
        stage.append(node, close);
        frame.append(stage);
        mediaLive.replaceChildren(frame);
        mediaLive.hidden = false;
    });

    root.querySelectorAll('[data-add-rows]').forEach((button) => {
        button.addEventListener('click', () => {
            const group = button.getAttribute('data-add-rows');
            const row = root.querySelector('[data-extra="' + group + '"]');
            if (!row) return;
            row.classList.remove('hidden');
            row.removeAttribute('data-extra');
            const field = row.querySelector('input[type="text"], textarea');
            if (field) field.focus();
            sync();
        });
    });

    root.addEventListener('change', (event) => {
        const target = event.target;
        if (target && target.name === 'requires_review') {
            root.dataset.reviewTouched = '1';
        }
        if (target && target.hasAttribute('data-scenario-written') && target.checked && !root.dataset.reviewTouched) {
            const box = root.querySelector('input[type="checkbox"][name="requires_review"]');
            if (box) box.checked = true;
        }
        sync();
    });
    typeInput.addEventListener('change', () => {
        const type = typeInput.value;
        const box = root.querySelector('input[type="checkbox"][name="requires_review"]');
        if (box && !root.dataset.reviewTouched && (type === 'short_answer' || (type === 'scenario' && written && written.checked))) {
            box.checked = true;
        }
        sync();
    });
    sync();
    if (window.location.hash === '#quiz-builder') {
        root.scrollIntoView({ block: 'start' });
    }
})();
</script>

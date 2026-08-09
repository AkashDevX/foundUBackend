<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use App\Models\TrainingAssignment;
use App\Models\TrainingModule;
use App\Models\TrainingPage;
use App\Models\TrainingPageSection;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuestionOption;
use App\Support\AdminTraining;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminTrainingController extends Controller
{
    private const STEPS = ['overview', 'study', 'questions', 'assign'];

    public function index(Request $request): View
    {
        $ctx = $this->tenantContext($request);
        $conn = $ctx['conn'];

        $modules = TrainingModule::on($conn)
            ->withCount(['pages', 'questions', 'assignments'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (TrainingModule $module) use ($conn): array {
                $module->setRelation(
                    'assignments',
                    TrainingAssignment::on($conn)
                        ->where('training_module_id', $module->id)
                        ->with('attempt')
                        ->get()
                );

                return [
                    'module' => $module,
                    'summary' => AdminTraining::moduleResultsSummary($module),
                ];
            });

        return view('admin.training.index', [
            'company' => $ctx['company'],
            'modules' => $modules,
        ]);
    }

    public function create(Request $request): View
    {
        $ctx = $this->tenantContext($request);

        return view('admin.training.form', [
            'company' => $ctx['company'],
            'module' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $data = $this->validatedModule($request);

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');

        $module = TrainingModule::on($ctx['conn'])->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'pass_percent' => $data['pass_percent'],
            'question_time_seconds' => $data['question_time_seconds'],
            'created_by' => $portalUser->name,
        ]);

        return redirect()
            ->route('admin.training.show', ['module' => $module->id, 'step' => 'study'])
            ->with('status', 'Module created. Add study pages next.');
    }

    public function show(Request $request, int $module): View
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])
            ->with(['pages.sections', 'questions.options'])
            ->findOrFail($module);

        $step = $this->resolveStep($request);

        $employees = AdminTraining::activeEmployees($ctx['conn']);
        $assignedIds = TrainingAssignment::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $editingPage = null;
        $editingSection = null;
        if ($step === 'study' && $request->query('edit_page')) {
            $editingPage = TrainingPage::on($ctx['conn'])
                ->where('training_module_id', $trainingModule->id)
                ->with('sections')
                ->find($request->query('edit_page'));

            if ($editingPage && $request->query('edit_section')) {
                $editingSection = TrainingPageSection::on($ctx['conn'])
                    ->where('training_page_id', $editingPage->id)
                    ->find($request->query('edit_section'));
            }
        }

        return view('admin.training.show', [
            'company' => $ctx['company'],
            'module' => $trainingModule,
            'step' => $step,
            'steps' => self::STEPS,
            'employees' => $employees,
            'assignedIds' => $assignedIds,
            'editingPage' => $editingPage,
            'editingSection' => $editingSection,
            'canAssign' => $trainingModule->pages->count() > 0
                && $trainingModule->questions->count() > 0,
            'isPublished' => $trainingModule->isPublished(),
        ]);
    }

    public function update(Request $request, int $module): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $data = $this->validatedModule($request);

        if ($data['status'] === 'published') {
            if ($trainingModule->pages()->count() < 1) {
                throw ValidationException::withMessages([
                    'status' => 'Add at least one study page before publishing.',
                ]);
            }
            if ($trainingModule->questions()->count() < 1) {
                throw ValidationException::withMessages([
                    'status' => 'Add at least one question before publishing.',
                ]);
            }
        }

        $trainingModule->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'pass_percent' => $data['pass_percent'],
            'question_time_seconds' => $data['question_time_seconds'],
        ]);
        $trainingModule->save();

        $next = $request->input('next_step');
        $step = in_array($next, self::STEPS, true) ? $next : 'overview';

        return redirect()
            ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => $step])
            ->with('status', 'Overview saved.');
    }

    public function destroy(Request $request, int $module): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $trainingModule->delete();

        return redirect()
            ->route('admin.training.index')
            ->with('status', 'Training module deleted.');
    }

    public function storePage(Request $request, int $module): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $data = $this->validatedPage($request);

        $maxOrder = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->max('sort_order');

        $page = TrainingPage::on($ctx['conn'])->create([
            'training_module_id' => $trainingModule->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'sort_order' => is_numeric($maxOrder) ? ((int) $maxOrder) + 1 : 0,
        ]);

        return redirect()
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $page->id,
            ])
            ->with('status', 'Study page added. Optionally add toggleable subtopics below.');
    }

    public function updatePage(Request $request, int $module, int $page): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($page);
        $data = $this->validatedPage($request);

        $row->fill([
            'title' => $data['title'],
            'body' => $data['body'],
        ]);
        $row->save();

        return redirect()
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $row->id,
            ])
            ->with('status', 'Study page updated.');
    }

    public function destroyPage(Request $request, int $module, int $page): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($page);
        $row->delete();

        return redirect()
            ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => 'study'])
            ->with('status', 'Study page removed.');
    }

    public function movePage(Request $request, int $module, int $page): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $direction = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ])['direction'];

        $pages = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $pages->search(fn (TrainingPage $p) => (int) $p->id === $page);
        if ($index === false) {
            abort(404);
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $pages->count()) {
            return redirect()
                ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => 'study']);
        }

        $a = $pages[$index];
        $b = $pages[$swapWith];
        $tmp = $a->sort_order;
        $a->sort_order = $b->sort_order;
        $b->sort_order = $tmp;
        // Ensure distinct orders if equal
        if ((int) $a->sort_order === (int) $b->sort_order) {
            $a->sort_order = $index;
            $b->sort_order = $swapWith;
        }
        $a->save();
        $b->save();

        return redirect()
            ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => 'study'])
            ->with('status', 'Page order updated.');
    }

    public function storeSection(Request $request, int $module, int $page): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $pageRow = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($page);
        $data = $this->validatedSection($request);

        $maxOrder = TrainingPageSection::on($ctx['conn'])
            ->where('training_page_id', $pageRow->id)
            ->max('sort_order');

        TrainingPageSection::on($ctx['conn'])->create([
            'training_page_id' => $pageRow->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'sort_order' => is_numeric($maxOrder) ? ((int) $maxOrder) + 1 : 0,
        ]);

        return redirect()
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $pageRow->id,
            ])
            ->with('status', 'Subtopic added.');
    }

    public function updateSection(Request $request, int $module, int $page, int $section): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $pageRow = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($page);
        $row = TrainingPageSection::on($ctx['conn'])
            ->where('training_page_id', $pageRow->id)
            ->findOrFail($section);
        $data = $this->validatedSection($request);

        $row->fill([
            'title' => $data['title'],
            'body' => $data['body'],
        ]);
        $row->save();

        return redirect()
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $pageRow->id,
            ])
            ->with('status', 'Subtopic updated.');
    }

    public function destroySection(Request $request, int $module, int $page, int $section): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $pageRow = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($page);
        $row = TrainingPageSection::on($ctx['conn'])
            ->where('training_page_id', $pageRow->id)
            ->findOrFail($section);
        $row->delete();

        return redirect()
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $pageRow->id,
            ])
            ->with('status', 'Subtopic removed.');
    }

    public function moveSection(Request $request, int $module, int $page, int $section): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $pageRow = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($page);
        $direction = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ])['direction'];

        $sections = TrainingPageSection::on($ctx['conn'])
            ->where('training_page_id', $pageRow->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $sections->search(fn (TrainingPageSection $s) => (int) $s->id === $section);
        if ($index === false) {
            abort(404);
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $sections->count()) {
            return redirect()
                ->route('admin.training.show', [
                    'module' => $trainingModule->id,
                    'step' => 'study',
                    'edit_page' => $pageRow->id,
                ]);
        }

        $a = $sections[$index];
        $b = $sections[$swapWith];
        $tmp = $a->sort_order;
        $a->sort_order = $b->sort_order;
        $b->sort_order = $tmp;
        if ((int) $a->sort_order === (int) $b->sort_order) {
            $a->sort_order = $index;
            $b->sort_order = $swapWith;
        }
        $a->save();
        $b->save();

        return redirect()
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $pageRow->id,
            ])
            ->with('status', 'Subtopic order updated.');
    }

    public function storeQuestion(Request $request, int $module): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $data = $this->validatedQuestion($request);

        DB::connection($ctx['conn'])->transaction(function () use ($ctx, $trainingModule, $data): void {
            $maxOrder = TrainingQuestion::on($ctx['conn'])
                ->where('training_module_id', $trainingModule->id)
                ->max('sort_order');

            $question = TrainingQuestion::on($ctx['conn'])->create([
                'training_module_id' => $trainingModule->id,
                'question_text' => $data['question_text'],
                'sort_order' => is_numeric($maxOrder) ? ((int) $maxOrder) + 1 : 0,
                'points' => $data['points'],
            ]);

            foreach ($data['options'] as $index => $option) {
                TrainingQuestionOption::on($ctx['conn'])->create([
                    'training_question_id' => $question->id,
                    'option_text' => $option['text'],
                    'is_correct' => (int) $data['correct_index'] === $index,
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()
            ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => 'questions'])
            ->with('status', 'Question added.');
    }

    public function destroyQuestion(Request $request, int $module, int $question): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingQuestion::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($question);
        $row->delete();

        return redirect()
            ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => 'questions'])
            ->with('status', 'Question removed.');
    }

    public function assign(Request $request, int $module): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])
            ->with(['pages', 'questions'])
            ->findOrFail($module);

        if ($trainingModule->pages->count() < 1) {
            throw ValidationException::withMessages([
                'pages' => 'Add at least one study page before assigning.',
            ]);
        }
        if ($trainingModule->questions->count() < 1) {
            throw ValidationException::withMessages([
                'questions' => 'Add at least one question before assigning.',
            ]);
        }

        // Assigning from step 4 publishes automatically so admins don't bounce back to Overview.
        if (! $trainingModule->isPublished()) {
            $trainingModule->status = 'published';
            $trainingModule->save();
        }

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer'],
            'due_date' => ['nullable', 'date'],
        ]);

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');

        $employeeIds = collect($data['employee_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $employees = Employee::on($ctx['conn'])
            ->whereIn('id', $employeeIds)
            ->where('employment_status', 'active')
            ->get()
            ->keyBy('id');

        if ($employees->count() !== $employeeIds->count()) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Select only active employees.',
            ]);
        }

        $existing = TrainingAssignment::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->whereIn('employee_id', $employeeIds)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $created = 0;
        foreach ($employeeIds as $employeeId) {
            if (in_array($employeeId, $existing, true)) {
                continue;
            }
            TrainingAssignment::on($ctx['conn'])->create([
                'training_module_id' => $trainingModule->id,
                'employee_id' => $employeeId,
                'assigned_by' => $portalUser->name,
                'assigned_at' => now(),
                'due_date' => $data['due_date'] ?? null,
            ]);
            $created++;
        }

        $skipped = count($existing);
        $message = $created > 0
            ? "Assigned to {$created} employee(s)."
            : 'No new assignments (selected employees already have this module).';
        if ($skipped > 0 && $created > 0) {
            $message .= " {$skipped} already assigned were skipped.";
        }

        return redirect()
            ->route('admin.training.results', $trainingModule->id)
            ->with('status', $message);
    }

    public function results(Request $request, int $module): View
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $trainingModule->setRelation(
            'assignments',
            TrainingAssignment::on($ctx['conn'])
                ->where('training_module_id', $trainingModule->id)
                ->with(['employee', 'attempt.answers'])
                ->get()
        );

        return view('admin.training.results', [
            'company' => $ctx['company'],
            'module' => $trainingModule,
            'summary' => AdminTraining::moduleResultsSummary($trainingModule),
            'rows' => AdminTraining::moduleResultRows($trainingModule),
        ]);
    }

    public function resetAttempt(Request $request, int $module, int $assignment): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingAssignment::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($assignment);

        AdminTraining::resetAttempt($row);

        return redirect()
            ->route('admin.training.results', $trainingModule->id)
            ->with('status', 'Attempt reset. The employee can study and retake with a new question order.');
    }

    public function destroyAssignment(Request $request, int $module, int $assignment): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingAssignment::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($assignment);
        $row->delete();

        return redirect()
            ->route('admin.training.results', $trainingModule->id)
            ->with('status', 'Assignment removed.');
    }

    /**
     * @return array{company: \App\Models\Company, conn: string}
     */
    private function tenantContext(Request $request): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();

        return [
            'company' => $company,
            'conn' => $company->tenant_connection,
        ];
    }

    private function resolveStep(Request $request): string
    {
        $step = $request->query('step', 'overview');

        return in_array($step, self::STEPS, true) ? $step : 'overview';
    }

    /**
     * @return array{title: string, description: ?string, status: string, pass_percent: int, question_time_seconds: int}
     */
    private function validatedModule(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'pass_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'question_time_seconds' => ['nullable', 'integer', 'min:10', 'max:600'],
        ]);

        $data['title'] = trim($data['title']);
        $data['description'] = isset($data['description']) ? trim((string) $data['description']) : null;
        if ($data['description'] === '') {
            $data['description'] = null;
        }
        $data['pass_percent'] = array_key_exists('pass_percent', $data) && $data['pass_percent'] !== null
            ? (int) $data['pass_percent']
            : 70;
        $data['question_time_seconds'] = array_key_exists('question_time_seconds', $data) && $data['question_time_seconds'] !== null
            ? (int) $data['question_time_seconds']
            : 45;

        return $data;
    }

    /**
     * @return array{title: string, body: string}
     */
    private function validatedPage(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
        ]);

        return [
            'title' => trim($data['title']),
            'body' => trim((string) ($data['body'] ?? '')),
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    private function validatedSection(Request $request): array
    {
        $data = $request->validate([
            'section_title' => ['required', 'string', 'max:200'],
            'section_body' => ['required', 'string', 'max:20000'],
        ]);

        return [
            'title' => trim($data['section_title']),
            'body' => trim($data['section_body']),
        ];
    }

    /**
     * @return array{question_text: string, points: int, options: list<array{text: string}>, correct_index: int}
     */
    private function validatedQuestion(Request $request): array
    {
        $data = $request->validate([
            'question_text' => ['required', 'string', 'max:2000'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*.text' => ['required', 'string', 'max:500'],
            'correct_index' => ['required', 'integer', 'min:0'],
        ]);

        $options = [];
        foreach ($data['options'] as $option) {
            $text = trim((string) $option['text']);
            if ($text === '') {
                continue;
            }
            $options[] = ['text' => $text];
        }

        if (count($options) < 2) {
            throw ValidationException::withMessages([
                'options' => 'Add at least two answer options.',
            ]);
        }

        $correctIndex = (int) $data['correct_index'];
        if ($correctIndex < 0 || $correctIndex >= count($options)) {
            throw ValidationException::withMessages([
                'correct_index' => 'Mark which option is correct.',
            ]);
        }

        return [
            'question_text' => trim($data['question_text']),
            'points' => isset($data['points']) ? (int) $data['points'] : 1,
            'options' => $options,
            'correct_index' => $correctIndex,
        ];
    }
}

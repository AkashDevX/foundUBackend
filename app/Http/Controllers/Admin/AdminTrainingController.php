<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use App\Models\TrainingAssignment;
use App\Models\TrainingCertificate;
use App\Models\TrainingModule;
use App\Models\TrainingPage;
use App\Models\TrainingPageSection;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuestionOption;
use App\Models\TrainingSlideBlock;
use App\Support\AdminTraining;
use App\Support\InductionEligibility;
use App\Support\TrainingAssignmentNotice;
use App\Support\TrainingCertificates;
use App\Support\TrainingQuiz;
use App\Support\TrainingSlideBlocks;
use App\Support\TrainingSlideMedia;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminTrainingController extends Controller
{
    private const STEPS = ['overview', 'study', 'questions', 'assign'];

    public function index(Request $request): View
    {
        $ctx = $this->tenantContext($request);
        $conn = $ctx['conn'];
        $hasInduction = Schema::connection($conn)->hasColumn('training_modules', 'is_induction');

        if ($hasInduction) {
            /** @var OrganizationPortalUser $portalUser */
            $portalUser = $request->user('portal');
            InductionEligibility::ensureSingleton($conn, $portalUser->name ?: $portalUser->email);
        }

        $modules = TrainingModule::on($conn)
            ->withCount(['pages', 'questions', 'assignments'])
            ->when($hasInduction, fn ($query) => $query->orderByDesc('is_induction'))
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

    public function certificates(Request $request): View
    {
        $ctx = $this->tenantContext($request);
        $certificates = collect();
        if (Schema::connection($ctx['conn'])->hasTable('training_certificates')) {
            TrainingModule::on($ctx['conn'])
                ->get()
                ->each(fn (TrainingModule $module) => TrainingCertificates::issueOutstanding($module, $ctx['company']->name));

            $certificates = TrainingCertificate::on($ctx['conn'])
                ->with(['employee', 'assignment.module'])
                ->orderByDesc('completed_on')
                ->orderByDesc('id')
                ->get();
        }

        return view('admin.training.certificates', [
            'company' => $ctx['company'],
            'certificates' => $certificates,
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
            'max_attempts' => 1,
            'quiz_required' => true,
            'allow_retakes' => false,
            'question_time_seconds' => $data['question_time_seconds'],
            'issues_certificate' => $data['issues_certificate'],
            'certificate_validity_months' => $data['certificate_validity_months'],
            'created_by' => $portalUser->name,
        ]);

        return redirect()
            ->route('admin.training.show', ['module' => $module->id, 'step' => 'study'])
            ->with('status', 'Module created. Add study pages next.');
    }

    public function induction(Request $request): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $trainingModule = InductionEligibility::ensureSingleton($ctx['conn'], $portalUser->name ?: $portalUser->email);

        return redirect()->route('admin.training.show', ['module' => $trainingModule->id] + $request->query());
    }

    public function show(Request $request, int $module): View
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])
            ->with(['pages.blocks', 'pages.sections.blocks', 'questions.options'])
            ->findOrFail($module);

        return $this->renderModule($request, $trainingModule);
    }

    private function renderModule(Request $request, TrainingModule $trainingModule): View
    {
        $ctx = $this->tenantContext($request);
        $trainingModule->loadMissing(['pages.blocks', 'pages.sections.blocks', 'questions.options']);

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
                ->with(['blocks', 'sections.blocks'])
                ->find($request->query('edit_page'));

            if ($editingPage && $request->query('edit_section')) {
                $editingSection = TrainingPageSection::on($ctx['conn'])
                    ->with('blocks')
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
                && (! (bool) ($trainingModule->quiz_required ?? true) || $trainingModule->questions->count() > 0),
            'editingQuestion' => $step === 'questions' && $request->query('edit_question')
                ? TrainingQuestion::on($ctx['conn'])
                    ->where('training_module_id', $trainingModule->id)
                    ->with('options')
                    ->find($request->query('edit_question'))
                : null,
            'questionTypes' => TrainingQuiz::TYPE_LABELS,
            'isPublished' => $trainingModule->isPublished(),
        ]);
    }

    public function update(Request $request, int $module): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $data = $this->validatedModule($request, $trainingModule);

        if ($data['status'] === 'published') {
            if ($trainingModule->pages()->count() < 1) {
                throw ValidationException::withMessages([
                    'status' => 'Add at least one study page before publishing.',
                ]);
            }
            $quizRequired = (bool) ($trainingModule->quiz_required ?? true);
            if ($quizRequired && $trainingModule->questions()->count() < 1) {
                throw ValidationException::withMessages([
                    'status' => 'Add at least one quiz question before publishing, or make the quiz optional.',
                ]);
            }
        }

        $trainingModule->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'pass_percent' => $data['pass_percent'],
            'max_attempts' => $data['max_attempts'],
            'question_time_seconds' => $data['question_time_seconds'],
            'issues_certificate' => $data['issues_certificate'],
            'certificate_validity_months' => $data['certificate_validity_months'],
        ]);
        $trainingModule->save();
        $issued = TrainingCertificates::issueOutstanding($trainingModule, $ctx['company']->name);
        $assigned = $trainingModule->is_induction
            ? InductionEligibility::assignRequiredEmployees($trainingModule, $request->user('portal')?->name)
            : 0;

        $next = $request->input('next_step');
        $step = in_array($next, self::STEPS, true) ? $next : 'overview';

        $status = 'Overview saved.';
        if ($assigned > 0) {
            $status .= sprintf(' Induction assigned to %d employee(s) waiting to pass.', $assigned);
        }
        if ($issued > 0) {
            $status .= sprintf(' Certificate issued for %d completed employee(s).', $issued);
        }
        if ($trainingModule->is_induction && $data['status'] === 'published' && $trainingModule->questions()->count() !== InductionEligibility::RECOMMENDED_QUESTIONS) {
            $status .= sprintf(' Induction quizzes are usually %d questions.', InductionEligibility::RECOMMENDED_QUESTIONS);
        }

        return redirect()
            ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => $step])
            ->with('status', $status);
    }

    public function destroy(Request $request, int $module): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        if ($trainingModule->is_induction) {
            return redirect()
                ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => 'overview'])
                ->with('error', 'The induction module cannot be deleted. Set it back to draft if it should not be assigned yet.');
        }
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
        $incoming = TrainingSlideBlocks::incoming($request);

        $maxOrder = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->max('sort_order');

        $page = TrainingPage::on($ctx['conn'])->create([
            'training_module_id' => $trainingModule->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'bullets' => $data['bullets'] !== [] ? $data['bullets'] : null,
            'sort_order' => is_numeric($maxOrder) ? ((int) $maxOrder) + 1 : 0,
        ]);
        TrainingSlideBlocks::apply($page, $incoming);
        $this->saveInlineSections($request, $page);

        return redirect()
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $page->id,
            ])
            ->with('status', 'Slide added.');
    }

    public function updatePage(Request $request, int $module, int $page): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($page);
        $data = $this->validatedPage($request);
        $incoming = TrainingSlideBlocks::incoming($request);

        $row->fill([
            'title' => $data['title'],
            'body' => $data['body'],
            'bullets' => $data['bullets'] !== [] ? $data['bullets'] : null,
        ]);
        $row->save();
        $this->clearLegacyImage($row, $request->boolean('remove_image'));
        TrainingSlideBlocks::apply($row, $incoming);
        $this->saveInlineSections($request, $row);

        return redirect()
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $row->id,
            ])
            ->with('status', 'Slide updated.');
    }

    public function destroyPage(Request $request, int $module, int $page): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->with(['blocks', 'sections.blocks'])
            ->findOrFail($page);
        TrainingSlideMedia::delete($row->image_path);
        TrainingSlideBlocks::deleteOwnedFiles($row);
        foreach ($row->sections as $section) {
            TrainingSlideMedia::delete($section->image_path);
            TrainingSlideBlocks::deleteOwnedFiles($section);
        }
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
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $page,
            ])
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
        $incoming = TrainingSlideBlocks::incoming($request);

        $maxOrder = TrainingPageSection::on($ctx['conn'])
            ->where('training_page_id', $pageRow->id)
            ->max('sort_order');

        $section = TrainingPageSection::on($ctx['conn'])->create([
            'training_page_id' => $pageRow->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'sort_order' => is_numeric($maxOrder) ? ((int) $maxOrder) + 1 : 0,
        ]);
        TrainingSlideBlocks::apply($section, $incoming);

        return redirect()
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $pageRow->id,
                'edit_section' => $section->id,
            ])
            ->with('status', 'Toggle added.');
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
        $incoming = TrainingSlideBlocks::incoming($request);

        $row->fill([
            'title' => $data['title'],
            'body' => $data['body'],
        ]);
        $row->save();
        $this->clearLegacyImage($row, $request->boolean('remove_section_image'));
        TrainingSlideBlocks::apply($row, $incoming);

        return redirect()
            ->route('admin.training.show', [
                'module' => $trainingModule->id,
                'step' => 'study',
                'edit_page' => $pageRow->id,
                'edit_section' => $row->id,
            ])
            ->with('status', 'Toggle updated.');
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
            ->with('blocks')
            ->findOrFail($section);
        TrainingSlideMedia::delete($row->image_path);
        TrainingSlideBlocks::deleteOwnedFiles($row);
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

    public function updateQuiz(Request $request, int $module): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $data = $request->validate([
            'pass_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'question_time_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'quiz_required' => ['nullable', 'boolean'],
            'allow_retakes' => ['nullable', 'boolean'],
        ]);

        $maxAttempts = max(1, (int) $data['max_attempts']);
        $trainingModule->fill([
            'pass_percent' => (int) $data['pass_percent'],
            'question_time_seconds' => (int) $data['question_time_seconds'],
            'quiz_required' => true,
            'allow_retakes' => $trainingModule->is_induction ? true : $maxAttempts > 1,
            'max_attempts' => $maxAttempts,
        ]);
        $trainingModule->save();

        return redirect()
            ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => 'questions'])
            ->with('status', 'Quiz settings saved.');
    }

    public function storeQuestion(Request $request, int $module): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $data = TrainingQuiz::normalizeAdmin($request);

        DB::connection($ctx['conn'])->transaction(function () use ($ctx, $trainingModule, $data): void {
            $maxOrder = TrainingQuestion::on($ctx['conn'])
                ->where('training_module_id', $trainingModule->id)
                ->max('sort_order');

            $question = new TrainingQuestion;
            $question->setConnection($ctx['conn']);
            $question->training_module_id = $trainingModule->id;
            $question->sort_order = is_numeric($maxOrder) ? ((int) $maxOrder) + 1 : 0;
            $this->persistQuestion($question, $data);
        });

        return redirect()
            ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => 'questions'])
            ->with('status', 'Question added.');
    }

    public function updateQuestion(Request $request, int $module, int $question): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingQuestion::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($question);
        $data = TrainingQuiz::normalizeAdmin($request, $row);

        DB::connection($ctx['conn'])->transaction(function () use ($row, $data): void {
            $this->persistQuestion($row, $data);
        });

        return redirect()
            ->route('admin.training.show', ['module' => $trainingModule->id, 'step' => 'questions'])
            ->with('status', 'Question updated.');
    }

    public function destroyQuestion(Request $request, int $module, int $question): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingQuestion::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($question);
        TrainingSlideMedia::delete($row->media_path);
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
        if ((bool) ($trainingModule->quiz_required ?? true) && $trainingModule->questions->count() < 1) {
            throw ValidationException::withMessages([
                'questions' => 'Add at least one quiz question before assigning, or make the quiz optional.',
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
        $notifiedIds = [];
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
            $notifiedIds[] = (int) $employeeId;
        }

        $dueDate = isset($data['due_date']) && is_string($data['due_date']) ? $data['due_date'] : null;
        TrainingAssignmentNotice::notify(
            (string) $ctx['conn'],
            (string) $trainingModule->title,
            $notifiedIds,
            $dueDate,
        );

        $autoAssigned = InductionEligibility::assignRequiredEmployees($trainingModule, $portalUser->name);

        $skipped = count($existing);
        $message = $created > 0
            ? "Assigned to {$created} employee(s)."
            : 'No new assignments (selected employees already have this module).';
        if ($skipped > 0 && $created > 0) {
            $message .= " {$skipped} already assigned were skipped.";
        }
        if ($autoAssigned > 0) {
            $message .= " Induction also assigned to {$autoAssigned} employee(s) waiting to pass.";
        }

        return redirect()
            ->route('admin.training.results', $trainingModule->id)
            ->with('status', $message);
    }

    public function review(Request $request, int $module, int $assignment): View
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingAssignment::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->with(['employee', 'attempts.answers.question.options'])
            ->findOrFail($assignment);
        $row->setRelation('module', $trainingModule);

        return view('admin.training.review', [
            'company' => $ctx['company'],
            'module' => $trainingModule,
            'assignment' => $row,
            'attempts' => $row->attempts->sortByDesc('id')->values(),
        ]);
    }

    public function markReview(Request $request, int $module, int $assignment): RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingAssignment::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->findOrFail($assignment);
        $row->setRelation('module', $trainingModule);

        $data = $request->validate([
            'marks' => ['required', 'array'],
            'marks.*.correct' => ['nullable'],
            'marks.*.points' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        AdminTraining::applyReview($row, $data['marks'], $portalUser->name, $ctx['company']->name);

        return redirect()
            ->route('admin.training.assignments.review', [$trainingModule->id, $row->id])
            ->with('status', 'Responses marked. The score has been updated.');
    }

    public function questionMedia(Request $request, int $module, int $question): BinaryFileResponse
    {
        $ctx = $this->tenantContext($request);
        $row = TrainingQuestion::on($ctx['conn'])
            ->where('training_module_id', $module)
            ->findOrFail($question);

        return TrainingSlideMedia::fresh(TrainingSlideMedia::response($row->media_path));
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

        $rows = AdminTraining::moduleResultRows($trainingModule, $ctx['company']->name);
        if ($trainingModule->is_induction) {
            $rows = InductionEligibility::decorateResultRows($ctx['conn'], $rows);
        }

        return view('admin.training.results', [
            'company' => $ctx['company'],
            'module' => $trainingModule,
            'summary' => AdminTraining::moduleResultsSummary($trainingModule),
            'rows' => $rows,
        ]);
    }

    public function certificate(Request $request, int $module, int $assignment): View|RedirectResponse
    {
        $ctx = $this->tenantContext($request);
        $trainingModule = TrainingModule::on($ctx['conn'])->findOrFail($module);
        $row = TrainingAssignment::on($ctx['conn'])
            ->where('training_module_id', $trainingModule->id)
            ->with(['employee', 'attempt', 'module', 'certificate'])
            ->findOrFail($assignment);

        $certificate = TrainingCertificates::issueIfRequired($row, $ctx['company']->name);
        if ($certificate === null) {
            return redirect()
                ->route('admin.training.results', $trainingModule->id)
                ->with('error', 'A certificate is available after the employee passes a module that issues one.');
        }

        return view('admin.training.certificate', [
            'certificate' => $certificate,
            'module' => $trainingModule,
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
        InductionEligibility::onAdminReset($row);

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
     * @return array{company: Company, conn: string}
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
     * @return array{title: string, description: ?string, status: string, pass_percent: int, max_attempts: int, question_time_seconds: int, issues_certificate: bool, certificate_validity_months: ?int}
     */
    private function validatedModule(Request $request, ?TrainingModule $existing = null): array
    {
        $isInduction = (bool) ($existing?->is_induction ?? false);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'pass_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'question_time_seconds' => ['nullable', 'integer', 'min:10', 'max:600'],
        ]);

        $data['title'] = trim($data['title']);
        $data['description'] = isset($data['description']) ? trim((string) $data['description']) : null;
        if ($data['description'] === '') {
            $data['description'] = null;
        }
        $data['pass_percent'] = array_key_exists('pass_percent', $data) && $data['pass_percent'] !== null
            ? (int) $data['pass_percent']
            : ($isInduction ? InductionEligibility::DEFAULT_PASS_PERCENT : 70);
        $data['max_attempts'] = $isInduction
            ? (array_key_exists('max_attempts', $data) && $data['max_attempts'] !== null
                ? (int) $data['max_attempts']
                : (int) ($existing?->max_attempts ?: InductionEligibility::DEFAULT_MAX_ATTEMPTS))
            : max(1, (int) ($existing?->max_attempts ?: 1));
        $data['question_time_seconds'] = array_key_exists('question_time_seconds', $data) && $data['question_time_seconds'] !== null
            ? (int) $data['question_time_seconds']
            : (int) ($existing?->question_time_seconds ?: 45);
        $data['issues_certificate'] = true;
        $data['certificate_validity_months'] = null;

        return $data;
    }

    public function pageImage(Request $request, int $module, int $page): BinaryFileResponse
    {
        $ctx = $this->tenantContext($request);
        $row = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $module)
            ->findOrFail($page);

        return TrainingSlideMedia::response($row->image_path);
    }

    public function sectionImage(Request $request, int $module, int $page, int $section): BinaryFileResponse
    {
        $ctx = $this->tenantContext($request);
        $pageRow = TrainingPage::on($ctx['conn'])
            ->where('training_module_id', $module)
            ->findOrFail($page);
        $row = TrainingPageSection::on($ctx['conn'])
            ->where('training_page_id', $pageRow->id)
            ->findOrFail($section);

        return TrainingSlideMedia::response($row->image_path);
    }

    public function blockFile(Request $request, int $module, int $block): BinaryFileResponse
    {
        $ctx = $this->tenantContext($request);
        $row = TrainingSlideBlock::on($ctx['conn'])->with(['page', 'section.page'])->findOrFail($block);
        $page = $row->page ?? $row->section?->page;
        abort_unless($page !== null && (int) $page->training_module_id === $module, 404);

        return TrainingSlideMedia::response($row->file_path);
    }

    /**
     * @return array{title: string, body: string, bullets: list<string>}
     */
    private function validatedPage(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
            'bullets' => ['nullable', 'string', 'max:4000'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        return [
            'title' => trim($data['title']),
            'body' => trim((string) ($data['body'] ?? '')),
            'bullets' => TrainingSlideMedia::bulletsFromText($data['bullets'] ?? null),
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    private function validatedSection(Request $request): array
    {
        $data = $request->validate([
            'section_title' => ['required', 'string', 'max:200'],
            'section_body' => ['nullable', 'string', 'max:20000'],
            'remove_section_image' => ['nullable', 'boolean'],
        ]);

        return [
            'title' => trim($data['section_title']),
            'body' => trim((string) ($data['section_body'] ?? '')),
        ];
    }

    private function saveInlineSections(Request $request, TrainingPage $page): void
    {
        $page->loadMissing('sections');
        $input = $request->input('sections', []);
        $files = $request->file('sections', []);
        if (! is_array($input)) {
            $input = [];
        }
        if (! is_array($files)) {
            $files = [];
        }

        foreach ($page->sections as $section) {
            $payload = $input[$section->id] ?? $input[(string) $section->id] ?? null;
            if (! is_array($payload)) {
                continue;
            }
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title !== '') {
                $section->title = mb_substr($title, 0, 200);
            }
            $section->body = trim((string) ($payload['body'] ?? ''));
            $section->save();
            $this->clearLegacyImage($section, filter_var($payload['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN));
            $sectionFiles = $files[$section->id] ?? $files[(string) $section->id] ?? [];
            TrainingSlideBlocks::apply($section, TrainingSlideBlocks::fromPayload($payload, is_array($sectionFiles) ? $sectionFiles : []));
        }

        $newInput = $request->input('new_sections', []);
        $newFiles = $request->file('new_sections', []);
        if (! is_array($newInput)) {
            return;
        }
        if (! is_array($newFiles)) {
            $newFiles = [];
        }

        $maxOrder = TrainingPageSection::on($page->getConnectionName())
            ->where('training_page_id', $page->id)
            ->max('sort_order');
        $next = is_numeric($maxOrder) ? ((int) $maxOrder) + 1 : 0;

        foreach ($newInput as $index => $payload) {
            if (! is_array($payload)) {
                continue;
            }
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $section = TrainingPageSection::on($page->getConnectionName())->create([
                'training_page_id' => $page->id,
                'title' => mb_substr($title, 0, 200),
                'body' => trim((string) ($payload['body'] ?? '')),
                'sort_order' => $next,
            ]);
            $next++;
            $sectionFiles = $newFiles[$index] ?? [];
            TrainingSlideBlocks::apply($section, TrainingSlideBlocks::fromPayload($payload, is_array($sectionFiles) ? $sectionFiles : []));
        }
    }

    private function clearLegacyImage(TrainingPage|TrainingPageSection $row, bool $remove): void
    {
        if (! $remove || ! is_string($row->image_path) || $row->image_path === '') {
            return;
        }

        TrainingSlideMedia::delete($row->image_path);
        $row->image_path = null;
        $row->save();
    }

    /**
     * @param  array{
     *     question_type: string,
     *     question_text: string,
     *     prompt: string|null,
     *     points: int,
     *     explanation: string|null,
     *     requires_review: bool,
     *     accepted_answers: list<string>,
     *     options: list<array{text: string, is_correct: bool, match_text: string|null}>,
     *     media_kind: string|null,
     *     media_file: UploadedFile|null,
     *     remove_media?: bool
     * }  $data
     */
    private function persistQuestion(TrainingQuestion $question, array $data): void
    {
        $previousMedia = is_string($question->media_path) ? $question->media_path : null;
        $question->fill([
            'question_type' => $data['question_type'],
            'question_text' => $data['question_text'],
            'prompt' => $data['prompt'],
            'explanation' => $data['explanation'],
            'points' => $data['points'],
            'requires_review' => $data['requires_review'],
            'accepted_answers' => $data['accepted_answers'],
            'media_kind' => $data['media_kind'],
        ]);

        if ($data['media_file'] instanceof UploadedFile) {
            $question->media_path = TrainingSlideMedia::store($data['media_file'], 'questions');
        } elseif (($data['remove_media'] ?? false) || $data['media_kind'] === null) {
            $question->media_path = null;
        }
        $question->save();

        if ($previousMedia !== null && $previousMedia !== '' && $previousMedia !== $question->media_path) {
            TrainingSlideMedia::delete($previousMedia);
        }

        $connection = $question->getConnectionName();
        TrainingQuestionOption::on($connection)->where('training_question_id', $question->id)->delete();
        foreach ($data['options'] as $index => $option) {
            TrainingQuestionOption::on($connection)->create([
                'training_question_id' => $question->id,
                'option_text' => $option['text'],
                'match_text' => $option['match_text'],
                'is_correct' => $option['is_correct'],
                'sort_order' => $index,
            ]);
        }
    }
}

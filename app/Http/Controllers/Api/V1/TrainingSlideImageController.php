<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TrainingAssignment;
use App\Models\TrainingPage;
use App\Models\TrainingPageSection;
use App\Models\TrainingQuestion;
use App\Models\TrainingSlideBlock;
use App\Support\TrainingSlideMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrainingSlideImageController extends Controller
{
    public function page(Request $request, int $page): BinaryFileResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $connection = $employee->getConnectionName();
        $row = TrainingPage::on($connection)->findOrFail($page);
        $this->assertAssigned($connection, $employee, (int) $row->training_module_id);

        return TrainingSlideMedia::response($row->image_path);
    }

    public function section(Request $request, int $section): BinaryFileResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $connection = $employee->getConnectionName();
        $row = TrainingPageSection::on($connection)->with('page')->findOrFail($section);
        $moduleId = (int) ($row->page?->training_module_id ?? 0);
        $this->assertAssigned($connection, $employee, $moduleId);

        return TrainingSlideMedia::response($row->image_path);
    }

    public function question(Request $request, int $question): BinaryFileResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $connection = $employee->getConnectionName();
        $row = TrainingQuestion::on($connection)->findOrFail($question);
        $this->assertAssigned($connection, $employee, (int) $row->training_module_id);

        return TrainingSlideMedia::fresh(TrainingSlideMedia::response($row->media_path));
    }

    public function block(Request $request, int $block): BinaryFileResponse
    {
        $row = $this->assignedBlock($request, $block);

        return TrainingSlideMedia::response($row->file_path);
    }

    /**
     * Short-lived URL a video player can stream. The player cannot send the
     * Bearer token, and pulling the whole file through JavaScript fails on the phone.
     */
    public function openLink(Request $request, int $block): JsonResponse
    {
        $row = $this->assignedBlock($request, $block);
        abort_unless($row->kind === 'video', 404);

        $company = $request->tenantCompany();
        abort_unless($company instanceof Company, 422);

        URL::forceRootUrl($request->getSchemeAndHttpHost());

        $url = URL::temporarySignedRoute(
            'api.v1.training.blocks.open',
            now()->addMinutes(30),
            [
                'block' => $row->id,
                'company' => $company->slug,
            ],
        );

        return response()->json([
            'url' => $url,
            'expires_in' => 1800,
        ]);
    }

    public function openSigned(Request $request, int $block): BinaryFileResponse
    {
        $slug = trim((string) $request->query('company', ''));
        $company = Company::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
        abort_unless($company instanceof Company, 404);

        DB::setDefaultConnection($company->tenant_connection);

        $row = TrainingSlideBlock::query()->findOrFail($block);
        abort_unless($row->kind === 'video', 404);

        $response = TrainingSlideMedia::response($row->file_path);
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }

    private function assignedBlock(Request $request, int $block): TrainingSlideBlock
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $connection = $employee->getConnectionName();
        $row = TrainingSlideBlock::on($connection)->with(['page', 'section.page'])->findOrFail($block);
        $moduleId = (int) ($row->page?->training_module_id ?? $row->section?->page?->training_module_id ?? 0);
        $this->assertAssigned($connection, $employee, $moduleId);

        return $row;
    }

    private function assertAssigned(?string $connection, Employee $employee, int $moduleId): void
    {
        abort_unless(is_string($connection) && $connection !== '' && $moduleId > 0, 404);

        $assigned = TrainingAssignment::on($connection)
            ->where('employee_id', $employee->id)
            ->where('training_module_id', $moduleId)
            ->exists();

        abort_unless($assigned, 403);
    }
}

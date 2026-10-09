<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TrainingAssignment;
use App\Models\TrainingPage;
use App\Models\TrainingPageSection;
use App\Support\TrainingSlideMedia;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingSlideImageController extends Controller
{
    public function page(Request $request, int $page): StreamedResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $connection = $employee->getConnectionName();
        $row = TrainingPage::on($connection)->findOrFail($page);
        $this->assertAssigned($connection, $employee, (int) $row->training_module_id);

        return TrainingSlideMedia::response($row->image_path);
    }

    public function section(Request $request, int $section): StreamedResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $connection = $employee->getConnectionName();
        $row = TrainingPageSection::on($connection)->with('page')->findOrFail($section);
        $moduleId = (int) ($row->page?->training_module_id ?? 0);
        $this->assertAssigned($connection, $employee, $moduleId);

        return TrainingSlideMedia::response($row->image_path);
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

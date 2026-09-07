<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\JobTitle;
use App\Support\EmployeeJobTitles;
use App\Support\PayrollEmployeeRates;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeJobTitlesTest extends TestCase
{
    private string $connection = 'sqlite';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => $this->connection]);

        Schema::connection($this->connection)->dropIfExists('employee_job_title');
        Schema::connection($this->connection)->dropIfExists('employees');
        Schema::connection($this->connection)->dropIfExists('job_titles');

        Schema::connection($this->connection)->create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('employment_type')->nullable();
            $table->string('award_level')->nullable();
            $table->string('color')->nullable();
            $table->decimal('hourly_wage', 10, 2)->nullable();
            $table->string('rate_kind')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->nullable();
            $table->string('email')->nullable();
            $table->string('full_legal_name')->nullable();
            $table->string('job_title')->nullable();
            $table->unsignedBigInteger('job_title_id')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('award_level')->nullable();
            $table->string('employment_status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($this->connection)->create('employee_job_title', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('job_title_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['employee_id', 'job_title_id']);
        });
    }

    public function test_sync_assigns_multiple_titles_and_primary(): void
    {
        $ordinary = JobTitle::on($this->connection)->create([
            'name' => 'CAS1',
            'employment_type' => 'casual',
            'award_level' => 'level_1',
            'hourly_wage' => 32.31,
            'rate_kind' => 'weekday_ordinary',
            'is_active' => true,
        ]);
        $saturday = JobTitle::on($this->connection)->create([
            'name' => 'CAS1 (Saturday)',
            'employment_type' => 'casual',
            'award_level' => 'level_1',
            'hourly_wage' => 45.24,
            'rate_kind' => 'saturday',
            'is_active' => true,
        ]);

        $employee = Employee::on($this->connection)->create([
            'public_id' => 'emp-1',
            'email' => 'a@example.com',
            'full_legal_name' => 'Alex Example',
            'employment_status' => 'active',
        ]);

        EmployeeJobTitles::sync($this->connection, $employee, [$ordinary->id, $saturday->id], $saturday->id);
        $employee->refresh();

        $this->assertSame((int) $saturday->id, (int) $employee->job_title_id);
        $this->assertSame('CAS1 (Saturday)', $employee->job_title);
        $this->assertSame('casual', $employee->employment_type);
        $this->assertCount(2, $employee->jobTitles()->get());
        $this->assertTrue(PayrollEmployeeRates::employeeUsesTitleWages($employee->fresh(['assignedJobTitle', 'jobTitles'])));
        $this->assertSame(45.24, PayrollEmployeeRates::ordinaryHourlyRateForEmployee(
            $employee->fresh(['assignedJobTitle']),
            []
        ));
    }

    public function test_attach_to_title_keeps_existing_titles(): void
    {
        $ordinary = JobTitle::on($this->connection)->create([
            'name' => 'CAS1',
            'hourly_wage' => 32.31,
            'is_active' => true,
        ]);
        $midnight = JobTitle::on($this->connection)->create([
            'name' => 'CAS1 (Midnight Shift)',
            'hourly_wage' => 40.07,
            'is_active' => true,
        ]);

        $employee = Employee::on($this->connection)->create([
            'public_id' => 'emp-2',
            'email' => 'b@example.com',
            'full_legal_name' => 'Sam Example',
            'employment_status' => 'active',
        ]);

        EmployeeJobTitles::sync($this->connection, $employee, [$ordinary->id], $ordinary->id);
        EmployeeJobTitles::attachToTitle($this->connection, $midnight, [$employee->id]);
        $employee->refresh();

        $this->assertSame((int) $ordinary->id, (int) $employee->job_title_id);
        $this->assertEqualsCanonicalizing(
            [(int) $ordinary->id, (int) $midnight->id],
            $employee->jobTitles()->pluck('job_titles.id')->map(fn ($id) => (int) $id)->all()
        );
    }
}

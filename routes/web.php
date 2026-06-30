<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEmployeeAssignmentController;
use App\Http\Controllers\Admin\AdminEmployeeTasksController;
use App\Http\Controllers\Admin\AdminWeeklyScheduleController;
use App\Http\Controllers\Admin\AdminRegistrationDecisionController;
use App\Http\Controllers\Admin\AdminRegistrationFileController;
use App\Http\Controllers\Admin\AdminWorkforceController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformOrganizationRequestsController;
use App\Http\Controllers\Portal\OrganizationPortalAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/login', '/', 302)->name('login');

Route::middleware('guest:portal')->group(function (): void {
    Route::get('/', [OrganizationPortalAuthController::class, 'create'])->name('portal.login');
    Route::post('/login', [OrganizationPortalAuthController::class, 'store'])->name('portal.login.store');
});

Route::middleware('auth:portal')->group(function (): void {
    Route::post('/logout', [OrganizationPortalAuthController::class, 'destroy'])->name('portal.logout');

    Route::middleware('portal.platform')->prefix('platform')->name('platform.')->group(function (): void {
        Route::get('/', [PlatformDashboardController::class, 'index'])->name('dashboard');
        Route::get('/organizations/{slug}', [PlatformDashboardController::class, 'show'])->name('organizations.show');
        Route::get('/organization-requests', [PlatformOrganizationRequestsController::class, 'index'])->name('organization-requests.index');
        Route::get('/organization-requests/{organizationRequest}', [PlatformOrganizationRequestsController::class, 'show'])->name('organization-requests.show');
    });

    Route::middleware('portal.tenant')->group(function (): void {
    Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/workforce', [AdminWorkforceController::class, 'index'])->name('admin.workforce');
    Route::get('/admin/workforce/departments', [AdminWorkforceController::class, 'departments'])->name('admin.workforce.departments');
    Route::get('/admin/workforce/job-titles', [AdminWorkforceController::class, 'jobTitles'])->name('admin.workforce.job-titles');
    Route::get('/admin/workforce/work-locations', [AdminWorkforceController::class, 'workLocations'])->name('admin.workforce.work-locations');
    Route::get('/admin/workforce/shifts', [AdminWorkforceController::class, 'shifts'])->name('admin.workforce.shifts');
    Route::post('/admin/workforce/departments', [AdminWorkforceController::class, 'storeDepartment'])->name('admin.workforce.departments.store');
    Route::post('/admin/workforce/departments/{department}', [AdminWorkforceController::class, 'updateDepartment'])->name('admin.workforce.departments.update');
    Route::post('/admin/workforce/job-titles', [AdminWorkforceController::class, 'storeJobTitle'])->name('admin.workforce.job-titles.store');
    Route::post('/admin/workforce/job-titles/{jobTitle}', [AdminWorkforceController::class, 'updateJobTitle'])->name('admin.workforce.job-titles.update');
    Route::post('/admin/workforce/work-locations', [AdminWorkforceController::class, 'storeWorkLocation'])->name('admin.workforce.work-locations.store');
    Route::post('/admin/workforce/work-locations/{location}', [AdminWorkforceController::class, 'updateWorkLocation'])->name('admin.workforce.work-locations.update');
    Route::post('/admin/workforce/shifts', [AdminWorkforceController::class, 'storeShift'])->name('admin.workforce.shifts.store');
    Route::post('/admin/workforce/shifts/{shift}', [AdminWorkforceController::class, 'updateShift'])->name('admin.workforce.shifts.update');
    Route::post('/admin/workforce/geocode/reverse', [AdminWorkforceController::class, 'reverseGeocode'])
        ->middleware('throttle:120,1')
        ->name('admin.workforce.geocode.reverse');
    Route::get('/admin/workforce/geocode/search', [AdminWorkforceController::class, 'searchGeocode'])
        ->middleware('throttle:120,1')
        ->name('admin.workforce.geocode.search');
    Route::get('/admin/registrations/{companySlug}/{publicId}', [AdminDashboardController::class, 'show'])
        ->name('admin.registrations.show');
    Route::post('/admin/registrations/{companySlug}/{publicId}/assignment', [AdminEmployeeAssignmentController::class, 'update'])
        ->name('admin.registrations.assignment.update');
    Route::post('/admin/registrations/{companySlug}/{publicId}/profile', [AdminEmployeeAssignmentController::class, 'updateProfile'])
        ->name('admin.registrations.profile.update');
    Route::get('/admin/employees', [AdminEmployeeAssignmentController::class, 'assignments'])->name('admin.employees.assignments');
    Route::get('/admin/employees/weekly-schedule', [AdminWeeklyScheduleController::class, 'index'])->name('admin.employees.weekly-schedule');
    Route::post('/admin/employees/weekly-schedule/shifts', [AdminWeeklyScheduleController::class, 'storeShift'])->name('admin.employees.weekly-schedule.shifts.store');
    Route::post('/admin/employees/weekly-schedule/shifts/{scheduleShift}', [AdminWeeklyScheduleController::class, 'updateShift'])->name('admin.employees.weekly-schedule.shifts.update');
    Route::delete('/admin/employees/weekly-schedule/shifts/{scheduleShift}', [AdminWeeklyScheduleController::class, 'destroyShift'])->name('admin.employees.weekly-schedule.shifts.destroy');
    Route::post('/admin/employees/weekly-schedule/fill-from-assignments', [AdminWeeklyScheduleController::class, 'fillFromAssignments'])->name('admin.employees.weekly-schedule.fill-from-assignments');
    Route::get('/admin/employees/tasks', [AdminEmployeeTasksController::class, 'index'])->name('admin.employees.tasks');
    Route::post('/admin/employees/tasks', [AdminEmployeeTasksController::class, 'store'])->name('admin.employees.tasks.store');
    Route::post('/admin/employees/tasks/{taskAssignment}', [AdminEmployeeTasksController::class, 'update'])->name('admin.employees.tasks.update');
    Route::delete('/admin/employees/tasks/{taskAssignment}', [AdminEmployeeTasksController::class, 'destroy'])->name('admin.employees.tasks.destroy');
    Route::get('/admin/employees/time-clock', [AdminEmployeeAssignmentController::class, 'timeClock'])->name('admin.employees.time-clock');
    Route::post('/admin/employees/time-clock/timesheets/approve', [AdminEmployeeAssignmentController::class, 'approveTimesheet'])->name('admin.employees.time-clock.timesheets.approve');
    Route::post('/admin/employees/time-clock/timesheets/reject', [AdminEmployeeAssignmentController::class, 'rejectTimesheet'])->name('admin.employees.time-clock.timesheets.reject');
    Route::post('/admin/employees/{publicId}/assignment', [AdminEmployeeAssignmentController::class, 'updateFromList'])
        ->name('admin.employees.assignment.update');
    Route::post('/admin/registrations/{companySlug}/{publicId}/accept', [AdminRegistrationDecisionController::class, 'accept'])
        ->name('admin.registrations.accept');
    Route::post('/admin/registrations/{companySlug}/{publicId}/decline', [AdminRegistrationDecisionController::class, 'decline'])
        ->name('admin.registrations.decline');
    Route::get('/admin/registrations/{companySlug}/{publicId}/files/{slot}/{itemKey?}', [AdminRegistrationFileController::class, 'show'])
        ->name('admin.registration.file')
        ->where('slot', '[a-z\\-]+');
    });
});

Route::get('/welcome', function () {
    return view('welcome');
})->name('welcome');

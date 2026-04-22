<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEmployeeAssignmentController;
use App\Http\Controllers\Admin\AdminRegistrationDecisionController;
use App\Http\Controllers\Admin\AdminRegistrationFileController;
use App\Http\Controllers\Admin\AdminWorkforceController;
use App\Http\Controllers\Portal\OrganizationPortalAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/login', '/', 302)->name('login');

Route::middleware('guest:portal')->group(function (): void {
    Route::get('/', [OrganizationPortalAuthController::class, 'create'])->name('portal.login');
    Route::post('/login', [OrganizationPortalAuthController::class, 'store'])->name('portal.login.store');
});

Route::middleware('auth:portal')->group(function (): void {
    Route::post('/logout', [OrganizationPortalAuthController::class, 'destroy'])->name('portal.logout');
    Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/workforce', [AdminWorkforceController::class, 'index'])->name('admin.workforce');
    Route::post('/admin/workforce/departments', [AdminWorkforceController::class, 'storeDepartment'])->name('admin.workforce.departments.store');
    Route::post('/admin/workforce/work-locations', [AdminWorkforceController::class, 'storeWorkLocation'])->name('admin.workforce.work-locations.store');
    Route::post('/admin/workforce/shifts', [AdminWorkforceController::class, 'storeShift'])->name('admin.workforce.shifts.store');
    Route::get('/admin/registrations/{companySlug}/{publicId}', [AdminDashboardController::class, 'show'])
        ->name('admin.registrations.show');
    Route::post('/admin/registrations/{companySlug}/{publicId}/assignment', [AdminEmployeeAssignmentController::class, 'update'])
        ->name('admin.registrations.assignment.update');
    Route::post('/admin/registrations/{companySlug}/{publicId}/accept', [AdminRegistrationDecisionController::class, 'accept'])
        ->name('admin.registrations.accept');
    Route::post('/admin/registrations/{companySlug}/{publicId}/decline', [AdminRegistrationDecisionController::class, 'decline'])
        ->name('admin.registrations.decline');
    Route::get('/admin/registrations/{companySlug}/{publicId}/files/{slot}/{itemKey?}', [AdminRegistrationFileController::class, 'show'])
        ->name('admin.registration.file')
        ->where('slot', '[a-z\\-]+');
});

Route::get('/welcome', function () {
    return view('welcome');
})->name('welcome');

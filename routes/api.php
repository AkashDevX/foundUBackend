<?php

use App\Http\Controllers\Api\V1\AppBootstrapController;
use App\Http\Controllers\Api\V1\ClockInEmployeeController;
use App\Http\Controllers\Api\V1\ClockOutEmployeeController;
use App\Http\Controllers\Api\V1\CurrentEmployeeController;
use App\Http\Controllers\Api\V1\LoginEmployeeController;
use App\Http\Controllers\Api\V1\LogoutEmployeeController;
use App\Http\Controllers\Api\V1\RegisterEmployeeController;
use App\Http\Controllers\Api\V1\TimeClockStatusController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant-scoped API (master resolves tenant, then CRUD uses that DB)
|--------------------------------------------------------------------------
|
| HTTP header (default: X-Company-Slug) must match a row in the master
| companies.slug column. After lookup, the default DB connection points at
| that company's database so inserts/updates/deletes use the right schema.
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/bootstrap', AppBootstrapController::class);
});

Route::middleware('tenant')->prefix('v1')->group(function () {
    /*
     * Mobile: same X-Company-Slug header for all routes below.
     * - POST /register — never returns a token; client must stay logged-out until user submits POST /login.
     * - POST /login — only email+password grants a Bearer token (status must be active).
     * - Org approval does not log anyone in; RN must not treat approval as auth — user signs in manually.
     * - GET /me, POST /logout — Authorization: Bearer {token} from /login only.
     * - GET /time-clock/status, POST /time-clock/clock-in|clock-out — GPS geofence vs assigned work site.
     */
    Route::post('/register', RegisterEmployeeController::class);
    Route::post('/login', LoginEmployeeController::class);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', CurrentEmployeeController::class);
        Route::post('/logout', LogoutEmployeeController::class);

        Route::get('/time-clock/status', TimeClockStatusController::class);
        Route::post('/time-clock/clock-in', ClockInEmployeeController::class);
        Route::post('/time-clock/clock-out', ClockOutEmployeeController::class);
    });

    Route::get('/tenant/context', function () {
        $company = request()->tenantCompany();

        // Proof of routing: Laravel default connection + server session DB (works with zero tables).
        $pdoDatabase = DB::scalar('SELECT DATABASE()');

        return response()->json([
            'slug' => $company->slug,
            'name' => $company->name,
            'database_name_from_master_registry' => $company->database_name,
            'tenant_connection' => $company->tenant_connection,
            'laravel_default_connection' => DB::getDefaultConnection(),
            'laravel_configured_database_name' => DB::connection()->getDatabaseName(),
            // Same value MySQL reports for this connection — proves the PDO session targets that catalog.
            'mysql_session_database' => $pdoDatabase,
        ]);
    });
});

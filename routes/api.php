<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController, DashboardController, UsersController,
    EmployeesController, TimeOffController,
    ReportsController, ActivityController, NotificationsController,
    HealthCheckController, TestEventController, CommonController,
    RolesController, WorkScheduleController
};

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::prefix('v1')->name('api.')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Notifications — all authenticated users
        Route::get('/notifications', [NotificationsController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationsController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationsController::class, 'markAllRead']);

        // Shared lookups — all authenticated users
        Route::get('/departments', [CommonController::class, 'departments']);
        Route::get('/departments/org', [CommonController::class, 'departmentsOrg']);
        Route::get('/locations', [CommonController::class, 'locations']);

        // Work Schedule — readable by all, writable by super admin only
        Route::get('/work-schedule', [WorkScheduleController::class, 'index']);
        Route::get('/company-settings', [WorkScheduleController::class, 'getCompanySettings']);
        Route::middleware('super_admin')->group(function () {
            Route::put('/work-schedule', [WorkScheduleController::class, 'update']);
            Route::put('/company-settings', [WorkScheduleController::class, 'updateCompanySettings']);
        });

        // HR Employee Portal — all authenticated users
        Route::prefix('portal')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Api\HRPortalController::class, 'dashboard']);
            Route::get('/leave-balance', [\App\Http\Controllers\Api\HRPortalController::class, 'leaveBalance']);
            Route::post('/time-off', [\App\Http\Controllers\Api\HRPortalController::class, 'requestTimeOff']);
            Route::patch('/time-off/{id}/approve', [\App\Http\Controllers\Api\HRPortalController::class, 'approveTimeOff']);
            Route::patch('/time-off/{id}/reject', [\App\Http\Controllers\Api\HRPortalController::class, 'rejectTimeOff']);
            Route::get('/team-calendar', [\App\Http\Controllers\Api\HRPortalController::class, 'teamCalendar']);
            Route::get('/whos-in', [\App\Http\Controllers\Api\HRPortalController::class, 'whosIn']);
            Route::get('/holidays', [\App\Http\Controllers\Api\HRPortalController::class, 'holidays']);
            Route::get('/timesheet', [\App\Http\Controllers\Api\HRPortalController::class, 'timesheet']);
            Route::get('/profile', [\App\Http\Controllers\Api\HRPortalController::class, 'profile']);
            Route::post('/clock-in', [\App\Http\Controllers\Api\HRPortalController::class, 'clockIn']);
            Route::post('/clock-out', [\App\Http\Controllers\Api\HRPortalController::class, 'clockOut']);
            Route::get('/directory', [\App\Http\Controllers\Api\HRPortalController::class, 'getDirectory']);
            Route::post('/broadcast-holiday', [\App\Http\Controllers\Api\HRPortalController::class, 'broadcastHoliday']);
            // Attendance management (admin)
            Route::get('/attendance',             [\App\Http\Controllers\Api\HRPortalController::class, 'attendanceRecords']);
            Route::patch('/attendance/{id}/approve', [\App\Http\Controllers\Api\HRPortalController::class, 'approveAttendance']);
        });

        // ─── Super Admin only ──────────────────────────────────────────────────
        Route::middleware('super_admin')->group(function () {
            Route::get('/health', [HealthCheckController::class, 'index']);
            Route::post('/test/fire-event', [TestEventController::class, 'fireEvent']);
            Route::get('/dashboard', [DashboardController::class, 'index']);
            Route::get('/activity', [ActivityController::class, 'index']);
            Route::post('/departments', [CommonController::class, 'createDepartment']);
        });

        // ─── Users & Roles — manage_users permission ──────────────────────────
        Route::middleware('check_permission:manage_users')->group(function () {
            Route::apiResource('users', UsersController::class);
            Route::post('/users/{id}/reset-password', [UsersController::class, 'resetPassword']);
            Route::post('/users/{id}/notes', [UsersController::class, 'saveNotes']);
            Route::post('/users/{id}/revoke-session/{tokenId}', [UsersController::class, 'revokeSession']);
            Route::post('/users/{id}/revoke-all-sessions', [UsersController::class, 'revokeAllSessions']);
            Route::apiResource('roles', RolesController::class);
        });

        // ─── Employees — hr_records permission ────────────────────────────────
        Route::middleware('check_permission:hr_records')->group(function () {
            Route::apiResource('employees', EmployeesController::class)->except(['destroy']);
            Route::get('/employees/{id}/time-off', [EmployeesController::class, 'timeOff']);
        });

        // ─── Time Off — approve_timeoff permission ────────────────────────────
        Route::middleware('check_permission:approve_timeoff')->group(function () {
            Route::get('/time-off', [TimeOffController::class, 'index']);
            Route::get('/time-off/suspicious', [TimeOffController::class, 'suspicious']);
            Route::get('/time-off/{id}', [TimeOffController::class, 'show']);
            Route::post('/time-off', [TimeOffController::class, 'store']);
            Route::put('/time-off/{id}', [TimeOffController::class, 'update']);
            Route::patch('/time-off/{id}/approve', [TimeOffController::class, 'approve']);
            Route::patch('/time-off/{id}/reject', [TimeOffController::class, 'reject']);
            Route::patch('/time-off/{id}/revoke', [TimeOffController::class, 'revoke']);
            Route::patch('/time-off/{id}/reopen', [TimeOffController::class, 'reopen']);
            Route::patch('/time-off/{id}/cancel', [TimeOffController::class, 'cancel']);
            Route::get('/time-off/{id}/audit', [TimeOffController::class, 'audit']);
            Route::get('/time-off/employee/{employeeId}/summary', [TimeOffController::class, 'employeeSummary']);
        });

        // ─── Reports — reports permission ─────────────────────────────────────
        Route::middleware('check_permission:reports')->group(function () {
            Route::get('/reports/dashboard', [ReportsController::class, 'dashboard']);
            Route::get('/reports/employees', [ReportsController::class, 'employees']);
            Route::get('/reports/time-off', [ReportsController::class, 'timeOff']);
        });
    });
});

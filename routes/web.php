<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\TimeOffController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileTemplateController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\CompanyEntityController;
use App\Http\Controllers\WorkScheduleController;
use App\Http\Controllers\HolidayCalendarController;
use App\Http\Controllers\TimeOffPolicyController;
use App\Http\Controllers\OnboardingWorkflowController;
use App\Http\Controllers\EmployeeOnboardingController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceManagerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficeLocationController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftAssignmentController;

Route::get('/', [\App\Http\Controllers\PageController::class, 'welcome']);

// ZKTeco ADMS / push endpoints — devices POST punches here over the internet.
// No auth (devices can't log in) and CSRF-excepted (see bootstrap/app.php).
Route::match(['get', 'post'], '/iclock/cdata', [\App\Http\Controllers\ZktecoPushController::class, 'cdata']);
Route::match(['get', 'post'], '/iclock/getrequest', [\App\Http\Controllers\ZktecoPushController::class, 'getrequest']);
Route::match(['get', 'post'], '/iclock/devicecmd', [\App\Http\Controllers\ZktecoPushController::class, 'devicecmd']);
Route::match(['get', 'post'], '/iclock/ping', [\App\Http\Controllers\ZktecoPushController::class, 'ping']);

// Web Session Authentication Routes
Route::get('/login', [\App\Http\Controllers\PageController::class, 'showLogin'])->name('login');
Route::post('/login', [\App\Http\Controllers\PageController::class, 'login'])->name('login.post');

// SaaS: public agency signup ("Create your workspace")
Route::middleware('guest')->group(function () {
    Route::get('/register', [\App\Http\Controllers\Auth\RegisterTenantController::class, 'show'])->name('register');
    Route::post('/register', [\App\Http\Controllers\Auth\RegisterTenantController::class, 'store'])->name('register.post');
});
Route::post('/logout', [\App\Http\Controllers\PageController::class, 'logout'])->name('logout');

// Forgot / reset password (self-service — works for any account, employee or admin)
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'reset'])->name('password.store');
});

// Guest Invitation Routes
Route::middleware('guest')->group(function () {
    Route::get('invitation/{token}', [\App\Http\Controllers\InvitationController::class, 'showAcceptForm'])->name('invitation.accept');
    Route::post('invitation/{token}', [\App\Http\Controllers\InvitationController::class, 'accept'])->name('invitation.submit');
});

// Authenticated Routes (No Force Password Change Required)
Route::middleware(['auth'])->group(function() {
    Route::get('/password/change', [\App\Http\Controllers\PasswordChangeController::class, 'showChangeForm'])->name('password.change');
    Route::post('/password/change', [\App\Http\Controllers\PasswordChangeController::class, 'update'])->name('password.update');

    // Account security — a user's own active sessions + sign out other devices.
    // Admins only (not shown to regular employees).
    Route::middleware('role:super_admin,hr_admin')->group(function () {
        Route::get('/account/security', [\App\Http\Controllers\SessionManagementController::class, 'mySecurity'])->name('account.security');
        Route::post('/account/security/logout-others', [\App\Http\Controllers\SessionManagementController::class, 'logoutOtherDevices'])->name('account.logout-others');
    });

    // Admin: active-session management (force logout users / everyone).
    Route::middleware('role:super_admin,hr_admin')->prefix('admin/sessions')->name('admin.sessions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SessionManagementController::class, 'index'])->name('index');
        Route::delete('{session}', [\App\Http\Controllers\SessionManagementController::class, 'revoke'])->name('revoke');
        Route::post('user/{user}/revoke-all', [\App\Http\Controllers\SessionManagementController::class, 'revokeAllForUser'])->name('revoke-all');
        Route::post('force-logout-everyone', [\App\Http\Controllers\SessionManagementController::class, 'forceLogoutEveryone'])->name('force-logout-everyone')->middleware('role:super_admin');
    });
});

// Authenticated Routes
Route::middleware(['auth', 'force.password.change'])->group(function() {
    
    // 1. Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Announcements — admins post; everyone sees them on the dashboard.
    Route::get('all-announcements', [\App\Http\Controllers\AnnouncementController::class, 'all'])->name('announcements.all');
    // Any user can acknowledge announcements (so they stop auto-popping).
    Route::post('announcements/read-all', [\App\Http\Controllers\AnnouncementController::class, 'markAllRead'])->name('announcements.read-all');
    Route::post('announcements/{announcement}/read', [\App\Http\Controllers\AnnouncementController::class, 'markRead'])->name('announcements.read');
    Route::middleware('role:super_admin,hr_admin')->group(function () {
        Route::get('announcements', [\App\Http\Controllers\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('announcements/archived', [\App\Http\Controllers\AnnouncementController::class, 'archived'])->name('announcements.archived');
        Route::post('announcements', [\App\Http\Controllers\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('announcements/{announcement}', [\App\Http\Controllers\AnnouncementController::class, 'update'])->name('announcements.update');
        Route::post('announcements/{announcement}/toggle', [\App\Http\Controllers\AnnouncementController::class, 'toggle'])->name('announcements.toggle');
        Route::delete('announcements/{announcement}', [\App\Http\Controllers\AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::post('announcements/{announcement}/restore', [\App\Http\Controllers\AnnouncementController::class, 'restore'])->name('announcements.restore');
        Route::delete('announcements/{announcement}/force', [\App\Http\Controllers\AnnouncementController::class, 'forceDelete'])->name('announcements.force-delete');
    });

    // Feedback / issue reporting — any user submits; admins review & respond.
    Route::get('feedback', [\App\Http\Controllers\FeedbackController::class, 'mine'])->name('feedback.mine');
    Route::post('feedback', [\App\Http\Controllers\FeedbackController::class, 'store'])->name('feedback.store');
    Route::delete('feedback/{feedback}', [\App\Http\Controllers\FeedbackController::class, 'cancel'])->name('feedback.cancel');
    Route::middleware('role:super_admin,hr_admin')->group(function () {
        Route::get('admin/feedback', [\App\Http\Controllers\FeedbackController::class, 'adminIndex'])->name('feedback.admin');
        Route::post('admin/feedback/{feedback}/respond', [\App\Http\Controllers\FeedbackController::class, 'respond'])->name('feedback.respond');
    });

    // Quick Code Requests — employee asks HR to share a tool login/OTP code.
    Route::post('code-requests', [\App\Http\Controllers\CodeRequestController::class, 'quickRequest'])->name('code-requests.store');
    Route::get('my-codes', [\App\Http\Controllers\CodeRequestController::class, 'myCodeRequests'])->name('code-requests.my');
    Route::get('my-codes/json', [\App\Http\Controllers\CodeRequestController::class, 'myCodeRequestsJson'])->name('code-requests.my-json');
    Route::post('code-requests/{codeRequest}/cancel', [\App\Http\Controllers\CodeRequestController::class, 'cancel'])->name('code-requests.cancel');
    Route::middleware('role:super_admin,hr_admin')->group(function () {
        Route::get('admin/code-requests', [\App\Http\Controllers\CodeRequestController::class, 'pendingCodes'])->name('code-requests.pending');
        Route::get('admin/code-requests/json', [\App\Http\Controllers\CodeRequestController::class, 'pendingJson'])->name('code-requests.pending-json');
        Route::get('admin/code-requests/{codeRequest}/reveal', [\App\Http\Controllers\CodeRequestController::class, 'revealCode'])->name('code-requests.reveal');
        Route::post('admin/code-requests/{codeRequest}/resend', [\App\Http\Controllers\CodeRequestController::class, 'resendCode'])->name('code-requests.resend');
        Route::post('admin/code-requests/{codeRequest}/reason', [\App\Http\Controllers\CodeRequestController::class, 'updateRejection'])->name('code-requests.reason');
        Route::post('admin/code-requests/{codeRequest}/send', [\App\Http\Controllers\CodeRequestController::class, 'sendCode'])->name('code-requests.send');
        Route::post('admin/code-requests/{codeRequest}/reject', [\App\Http\Controllers\CodeRequestController::class, 'rejectCode'])->name('code-requests.reject');
    });

    // Employee: my leave-encashment history.
    Route::get('my-encashments', [\App\Http\Controllers\LeaveEncashmentController::class, 'myEncashments'])->name('leave-encashments.my');

    // Personal settings (notifications, appearance, security, preferences).
    Route::get('settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/notifications', [\App\Http\Controllers\SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::put('settings/appearance', [\App\Http\Controllers\SettingsController::class, 'updateAppearance'])->name('settings.appearance');
    Route::put('settings/password', [\App\Http\Controllers\SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::put('settings/preferences', [\App\Http\Controllers\SettingsController::class, 'updatePreferences'])->name('settings.preferences');

    // Equipment Requests — employee asks to take company equipment home.
    Route::get('equipment', [\App\Http\Controllers\EquipmentRequestController::class, 'index'])->name('equipment.index');
    Route::post('equipment', [\App\Http\Controllers\EquipmentRequestController::class, 'store'])->name('equipment.store');
    Route::middleware('role:super_admin,hr_admin')->group(function () {
        Route::get('admin/equipment', [\App\Http\Controllers\EquipmentRequestController::class, 'adminIndex'])->name('equipment.admin');
        Route::post('admin/equipment/{equipmentRequest}/approve', [\App\Http\Controllers\EquipmentRequestController::class, 'approve'])->name('equipment.approve');
        Route::post('admin/equipment/{equipmentRequest}/reject', [\App\Http\Controllers\EquipmentRequestController::class, 'reject'])->name('equipment.reject');
        Route::delete('admin/equipment/{equipmentRequest}', [\App\Http\Controllers\EquipmentRequestController::class, 'destroy'])->name('equipment.destroy');
    });

    // Org Chart — visual reporting hierarchy (all authenticated users)
    Route::get('org-chart', [EmployeeController::class, 'orgChart'])->name('org-chart');

    // Company-wide weekly work calendar (all authenticated users)
    Route::get('work-calendar', [\App\Http\Controllers\WorkCalendarController::class, 'index'])->name('work-calendar');

    // Report Center (Super Admin / HR Admin)
    Route::get('reports', [\App\Http\Controllers\ReportsController::class, 'index'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('reports.index');

    // Company Files library (view: all; upload/delete: admin — enforced in controller)
    Route::get('files', [\App\Http\Controllers\CompanyFileController::class, 'index'])->name('files.index');
    Route::post('files', [\App\Http\Controllers\CompanyFileController::class, 'store'])->name('files.store');
    Route::get('files/{file}/download', [\App\Http\Controllers\CompanyFileController::class, 'download'])->name('files.download');
    Route::delete('files/{file}', [\App\Http\Controllers\CompanyFileController::class, 'destroy'])->name('files.destroy');

    // 2. Employees Resource (secured via employee.access middleware)
    Route::get('employees/export', [EmployeeController::class, 'exportCsv'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('employees.export');
        
    Route::post('employees/import', [EmployeeController::class, 'importCsv'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('employees.import');

    Route::get('employees/pending-invitations', [EmployeeController::class, 'pendingInvitations'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('employees.pending-invitations');

    // Archive (deactivate) / restore — reversible alternative to permanent delete.
    Route::get('employees/archived', [EmployeeController::class, 'archived'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('employees.archived');
    Route::post('employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('employees.deactivate');
    Route::post('employees/{employee}/restore', [EmployeeController::class, 'restore'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('employees.restore');
    // Change a user's RBAC role — super admin only.
    Route::put('employees/{employee}/role', [EmployeeController::class, 'updateRole'])
        ->middleware(['role:super_admin'])
        ->name('employees.update-role');
    // Change a user's attendance mode (biometric / remote) — admins.
    Route::put('employees/{employee}/attendance-mode', [EmployeeController::class, 'updateAttendanceMode'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('employees.update-attendance-mode');
    // Hide / show an employee on all attendance sheets & reports — admins.
    Route::put('employees/{employee}/attendance-visibility', [EmployeeController::class, 'updateAttendanceVisibility'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('employees.update-attendance-visibility');
    // Bulk attendance-mode page — pick many employees, set them together.
    Route::get('attendance-mode', [EmployeeController::class, 'attendanceMode'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('employees.attendance-mode');
    Route::post('attendance-mode/bulk', [EmployeeController::class, 'bulkAttendanceMode'])
        ->middleware(['role:super_admin,hr_admin'])
        ->name('employees.attendance-mode.bulk');

    Route::resource('employees', EmployeeController::class)
        ->middleware(['employee.access']);

    // 3. Time Off Resource & Actions
    Route::post('time-off/on-behalf', [TimeOffController::class, 'onBehalf'])
        ->name('time-off.on-behalf');
    Route::post('time-off/{timeOffRequest}/approve', [TimeOffController::class, 'approve'])
        ->name('time-off.approve');
    Route::post('time-off/{timeOffRequest}/reject', [TimeOffController::class, 'reject'])
        ->name('time-off.reject');

    // Late-arrival penalty reversal (appeal) workflow.
    Route::post('lateness-deductions/{deduction}/request-reversal', [\App\Http\Controllers\LatenessDeductionController::class, 'requestReversal'])->name('lateness.request-reversal');
    Route::middleware('role:super_admin,hr_admin')->group(function () {
        Route::post('lateness-deductions/{deduction}/revert', [\App\Http\Controllers\LatenessDeductionController::class, 'revert'])->name('lateness.revert');
        Route::post('lateness-deductions/{deduction}/review-reversal', [\App\Http\Controllers\LatenessDeductionController::class, 'reviewReversal'])->name('lateness.review-reversal');
    });
    Route::post('time-off/{timeOffRequest}/change-policy', [TimeOffController::class, 'changePolicy'])
        ->name('time-off.change-policy');

    // Return early (leave curtailment): employee requests, HR/manager approves.
    Route::post('time-off/{timeOffRequest}/return', [TimeOffController::class, 'requestReturn'])
        ->name('time-off.return');
    Route::post('time-off/returns/{leaveReturn}/approve', [TimeOffController::class, 'approveReturn'])
        ->name('time-off.return.approve');
    Route::post('time-off/returns/{leaveReturn}/reject', [TimeOffController::class, 'rejectReturn'])
        ->name('time-off.return.reject');

    Route::get('time-off/team-calendar', [TimeOffController::class, 'teamCalendar'])
        ->name('time-off.team-calendar');

    Route::resource('time-off', TimeOffController::class);

    // On-demand report generator (attendance + leave PDF reports).
    Route::middleware('role:super_admin,hr_admin')->group(function () {
        Route::get('reports/generate', [\App\Http\Controllers\ReportGeneratorController::class, 'index'])->name('reports.generate');
        Route::post('reports/generate', [\App\Http\Controllers\ReportGeneratorController::class, 'generate'])->name('reports.generate.submit');
    });

    // HR documents (Lateness Review, Return to Work, …): build templates, fill
    // per employee with attendance prefill, sign in-app, keep on file as history.
    Route::middleware('role:super_admin,hr_admin')->prefix('hr-documents')->name('hr-documents.')->group(function () {
        Route::get('/', [\App\Http\Controllers\HrDocumentController::class, 'index'])->name('index');

        // Template builder (literal 'templates/*' — declared before the {document} catch-all)
        Route::get('templates/create', [\App\Http\Controllers\HrDocumentController::class, 'createTemplate'])->name('templates.create');
        Route::post('templates', [\App\Http\Controllers\HrDocumentController::class, 'storeTemplate'])->name('templates.store');
        Route::get('templates/{template}/edit', [\App\Http\Controllers\HrDocumentController::class, 'editTemplate'])->name('templates.edit');
        Route::put('templates/{template}', [\App\Http\Controllers\HrDocumentController::class, 'updateTemplate'])->name('templates.update');
        Route::delete('templates/{template}', [\App\Http\Controllers\HrDocumentController::class, 'destroyTemplate'])->name('templates.destroy');

        // Fill / manage filled documents ('create'/'preview' before the {document} catch-all)
        Route::get('create', [\App\Http\Controllers\HrDocumentController::class, 'create'])->name('create');
        Route::get('deleted', [\App\Http\Controllers\HrDocumentController::class, 'deleted'])->name('deleted');
        Route::post('preview', [\App\Http\Controllers\HrDocumentController::class, 'preview'])->name('preview');
        Route::post('/', [\App\Http\Controllers\HrDocumentController::class, 'store'])->name('store');
        Route::get('{document}/edit', [\App\Http\Controllers\HrDocumentController::class, 'edit'])->name('edit');
        Route::put('{document}', [\App\Http\Controllers\HrDocumentController::class, 'update'])->name('update');
        Route::get('{document}', [\App\Http\Controllers\HrDocumentController::class, 'show'])->name('show');
        Route::get('{document}/pdf', [\App\Http\Controllers\HrDocumentController::class, 'pdf'])->name('pdf');
        Route::get('{document}/docx', [\App\Http\Controllers\HrDocumentController::class, 'docx'])->name('docx');
        Route::post('{document}/send', [\App\Http\Controllers\HrDocumentController::class, 'send'])->name('send');
        Route::post('{document}/archive', [\App\Http\Controllers\HrDocumentController::class, 'archive'])->name('archive');
        Route::post('{document}/unarchive', [\App\Http\Controllers\HrDocumentController::class, 'unarchive'])->name('unarchive');
        Route::delete('{document}', [\App\Http\Controllers\HrDocumentController::class, 'destroy'])->name('destroy');
        Route::post('{document}/restore', [\App\Http\Controllers\HrDocumentController::class, 'restoreDocument'])->name('restore');
        Route::delete('{document}/force', [\App\Http\Controllers\HrDocumentController::class, 'forceDelete'])->name('force-delete');
    });

    // Company-wide work-from-home days (all employees remote on those dates).
    Route::middleware('role:super_admin,hr_admin')->group(function () {
        Route::get('company-wfh-days', [\App\Http\Controllers\CompanyWfhDayController::class, 'index'])->name('company-wfh-days.index');
        Route::post('company-wfh-days', [\App\Http\Controllers\CompanyWfhDayController::class, 'store'])->name('company-wfh-days.store');
        Route::delete('company-wfh-days/{companyWfhDay}', [\App\Http\Controllers\CompanyWfhDayController::class, 'destroy'])->name('company-wfh-days.destroy');
    });

    // Employee-facing signing of HR documents sent to them (any authenticated
    // user; each action is gated in-controller to the assigned signers).
    Route::get('my-documents', [\App\Http\Controllers\HrDocumentSignController::class, 'index'])->name('hr-documents.to-sign');
    Route::get('my-documents/{document}/pdf', [\App\Http\Controllers\HrDocumentSignController::class, 'pdf'])->name('hr-documents.my-pdf');
    Route::get('hr-documents/{document}/sign', [\App\Http\Controllers\HrDocumentSignController::class, 'show'])->name('hr-documents.sign');
    Route::post('hr-documents/{document}/sign', [\App\Http\Controllers\HrDocumentSignController::class, 'store'])->name('hr-documents.sign.store');

    // 4. Performance Reviews Resource & Actions
    Route::post('performance/self-review', [PerformanceController::class, 'storeSelfReview'])
        ->name('performance.storeSelfReview');
    Route::post('performance/manager-review/{reviewee}', [PerformanceController::class, 'storeManagerReview'])
        ->name('performance.storeManagerReview');
    Route::post('performance/{review}/share', [PerformanceController::class, 'share'])
        ->name('performance.share');
    Route::post('performance/{review}/sign', [PerformanceController::class, 'sign'])
        ->name('performance.sign');
    Route::post('performance/{review}/reopen', [PerformanceController::class, 'reopen'])
        ->name('performance.reopen');
    Route::post('performance/cycles', [PerformanceController::class, 'storeCycle'])
        ->name('performance.storeCycle');
    // create/edit/update/destroy are not implemented (reviews use the custom
    // POST actions above) — only index + show exist, so limit the resource.
    Route::resource('performance', PerformanceController::class)->only(['index', 'show']);

    // 5. HR Profile Templates (Dynamic Profiles)
    Route::post('profile-templates/assign', [ProfileTemplateController::class, 'assign'])->name('profile-templates.assign');
    Route::post('profile-templates/unassign', [ProfileTemplateController::class, 'unassign'])->name('profile-templates.unassign');
    Route::post('profile-templates/{profile_template}/sections', [ProfileTemplateController::class, 'storeSection'])->name('profile-templates.sections.store')->middleware('role:super_admin,hr_admin');
    Route::put('profile-sections/{section}', [ProfileTemplateController::class, 'updateSection'])->name('profile-sections.update')->middleware('role:super_admin,hr_admin');
    Route::delete('profile-sections/{section}', [ProfileTemplateController::class, 'destroySection'])->name('profile-sections.destroy')->middleware('role:super_admin,hr_admin');
    Route::post('profile-sections/{section}/fields', [ProfileTemplateController::class, 'storeField'])->name('profile-sections.fields.store')->middleware('role:super_admin,hr_admin');
    Route::put('profile-fields/{field}', [ProfileTemplateController::class, 'updateField'])->name('profile-fields.update')->middleware('role:super_admin,hr_admin');
    Route::delete('profile-fields/{field}', [ProfileTemplateController::class, 'destroyField'])->name('profile-fields.destroy')->middleware('role:super_admin,hr_admin');
    Route::resource('profile-templates', ProfileTemplateController::class)->middleware('role:super_admin,hr_admin');

    // Per-employee document uploads (Files → Uploads tab) — self or admin (enforced in controller)
    Route::post('employees/{employee}/documents', [\App\Http\Controllers\EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
    Route::get('employees/{employee}/documents/{document}/download', [\App\Http\Controllers\EmployeeDocumentController::class, 'download'])->name('employees.documents.download');
    Route::delete('employees/{employee}/documents/{document}', [\App\Http\Controllers\EmployeeDocumentController::class, 'destroy'])->name('employees.documents.destroy');

    // Pay reviews / salary history (Compensation tab) — admin records, self/admin view
    Route::post('employees/{employee}/pay-reviews', [\App\Http\Controllers\PayReviewController::class, 'store'])->name('employees.pay-reviews.store');
    Route::delete('employees/{employee}/pay-reviews/{payReview}', [\App\Http\Controllers\PayReviewController::class, 'destroy'])->name('employees.pay-reviews.destroy');

    // Workspace branding (white-label) — admin
    Route::middleware('role:super_admin,hr_admin')->group(function () {
        Route::get('workspace/branding', [\App\Http\Controllers\WorkspaceBrandingController::class, 'edit'])->name('workspace.branding');
        Route::put('workspace/branding', [\App\Http\Controllers\WorkspaceBrandingController::class, 'update'])->name('workspace.branding.update');

        // Billing & plans
        Route::get('billing', [\App\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
        Route::post('billing/subscribe', [\App\Http\Controllers\BillingController::class, 'subscribe'])->name('billing.subscribe');
    });

    // SaaS operator console — platform owner manages all agencies.
    Route::middleware('operator')->prefix('operator')->name('operator.')->group(function () {
        Route::get('/', [\App\Http\Controllers\OperatorController::class, 'index'])->name('index');
        Route::post('tenants/{tenant}/suspend', [\App\Http\Controllers\OperatorController::class, 'suspend'])->name('suspend');
        Route::post('tenants/{tenant}/activate', [\App\Http\Controllers\OperatorController::class, 'activate'])->name('activate');
        Route::post('tenants/{tenant}/plan', [\App\Http\Controllers\OperatorController::class, 'updatePlan'])->name('plan');
        Route::post('tenants/{tenant}/impersonate', [\App\Http\Controllers\OperatorController::class, 'impersonate'])->name('impersonate');
    });
    // Return from impersonation — allowed while acting as a tenant admin (session-guarded).
    Route::post('operator/stop-impersonating', [\App\Http\Controllers\OperatorController::class, 'stopImpersonating'])->name('operator.stop-impersonating');

    // Probation lifecycle (Job tab) — admin manages, self/admin view
    Route::get('probation', [\App\Http\Controllers\ProbationController::class, 'index'])->name('probation.index');
    Route::post('employees/{employee}/probation', [\App\Http\Controllers\ProbationController::class, 'store'])->name('employees.probation.store');
    Route::post('employees/{employee}/probation/{probation}/extend', [\App\Http\Controllers\ProbationController::class, 'extend'])->name('employees.probation.extend');
    Route::post('employees/{employee}/probation/{probation}/confirm', [\App\Http\Controllers\ProbationController::class, 'confirm'])->name('employees.probation.confirm');
    Route::post('employees/{employee}/probation/{probation}/fail', [\App\Http\Controllers\ProbationController::class, 'fail'])->name('employees.probation.fail');
    Route::post('employees/{employee}/probation/{probation}/note', [\App\Http\Controllers\ProbationController::class, 'note'])->name('employees.probation.note');
    Route::delete('employees/{employee}/probation/{probation}', [\App\Http\Controllers\ProbationController::class, 'destroy'])->name('employees.probation.destroy');

    Route::get('employees/{employee}/profile', [EmployeeProfileController::class, 'show'])->name('employees.profile');
    Route::get('employees/{employee}/profile/edit', [EmployeeProfileController::class, 'edit'])->name('employees.profile.edit');
    Route::put('employees/{employee}/profile', [EmployeeProfileController::class, 'update'])->name('employees.profile.update');
    Route::post('employees/{employee}/assign-default-policies', [App\Http\Controllers\TimeOffController::class, 'assignDefaultPolicies'])->name('employees.assign-default-policies');

    // Company Forms — employee side (fill assigned forms)
    Route::get('my-forms', [\App\Http\Controllers\FormSubmissionController::class, 'myForms'])->name('my-forms.index');

    // Company-form review — admins OR employees granted reviewer access (checked in controller).
    Route::get('my-reviews', [\App\Http\Controllers\CompanyFormController::class, 'myReviews'])->name('company-forms.my-reviews');
    Route::get('company-forms/{companyForm}/responses', [\App\Http\Controllers\CompanyFormController::class, 'responses'])->name('company-forms.responses');
    Route::get('form-submissions/{submission}', [\App\Http\Controllers\CompanyFormController::class, 'viewSubmission'])->name('company-forms.submission');
    Route::post('form-submissions/{submission}/review', [\App\Http\Controllers\CompanyFormController::class, 'reviewSubmission'])->name('company-forms.submission.review');
    Route::get('my-forms/{companyForm}', [\App\Http\Controllers\FormSubmissionController::class, 'fill'])->name('forms.fill');
    Route::post('my-forms/{companyForm}/save', [\App\Http\Controllers\FormSubmissionController::class, 'save'])->name('forms.save');
    Route::post('my-forms/{companyForm}/submit', [\App\Http\Controllers\FormSubmissionController::class, 'submit'])->name('forms.submit');
    Route::get('form-responses/{response}/download', [\App\Http\Controllers\FormSubmissionController::class, 'downloadFile'])->name('forms.response.download');

    // Company Policies — employee side (view, download, acknowledge / e-sign)
    Route::get('my-policies', [\App\Http\Controllers\PolicyAcknowledgmentController::class, 'myPolicies'])->name('my-policies.index');
    Route::get('policies/{companyPolicy}', [\App\Http\Controllers\PolicyAcknowledgmentController::class, 'view'])->name('policies.view');
    Route::post('policies/{companyPolicy}/acknowledge', [\App\Http\Controllers\PolicyAcknowledgmentController::class, 'acknowledge'])->name('policies.acknowledge');
    Route::get('policies/{companyPolicy}/download', [\App\Http\Controllers\CompanyPolicyController::class, 'download'])->name('policies.download');

    // Company calendar — every authenticated user sees the events published to them.
    Route::get('calendar', [\App\Http\Controllers\EventController::class, 'employeeCalendar'])->name('events.employee-calendar');
    Route::get('calendar/data', [\App\Http\Controllers\EventController::class, 'employeeCalendarData'])->name('events.employee-calendar-data');

    // Company Documents — employee library (access controlled inside controller)
    Route::get('document-library', [\App\Http\Controllers\CompanyDocumentController::class, 'employeeIndex'])->name('document-library.index');
    Route::get('document-library/{document}/download', [\App\Http\Controllers\CompanyDocumentController::class, 'download'])->name('document-library.download');
    Route::get('document-library/{document}/view', [\App\Http\Controllers\CompanyDocumentController::class, 'view'])->name('document-library.view');
    Route::get('document-library/{document}/read', [\App\Http\Controllers\CompanyDocumentController::class, 'readDocument'])->name('document-library.read');
    Route::get('document-library/{document}/filled', [\App\Http\Controllers\CompanyDocumentController::class, 'filled'])->name('document-library.filled');
    Route::post('document-library/{document}/acknowledge', [\App\Http\Controllers\CompanyDocumentController::class, 'acknowledge'])->name('document-library.acknowledge');

    // Documents for signature — signing flow (any participant). The admin list
    // is folded into Company Documents; the employee inbox lives in the Library.
    Route::get('documents/{documentRequest}', [\App\Http\Controllers\DocumentRequestController::class, 'show'])->name('documents.show');
    Route::get('documents/{documentRequest}/file', [\App\Http\Controllers\DocumentRequestController::class, 'file'])->name('documents.file');
    Route::get('documents/{documentRequest}/signed-data', [\App\Http\Controllers\DocumentRequestController::class, 'signedData'])->name('documents.signed-data');
    Route::get('documents/{documentRequest}/sign', [\App\Http\Controllers\DocumentRequestController::class, 'sign'])->name('documents.sign');
    Route::post('documents/{documentRequest}/sign', [\App\Http\Controllers\DocumentRequestController::class, 'storeSignature'])->name('documents.sign.store');
    Route::post('documents/{documentRequest}/decline', [\App\Http\Controllers\DocumentRequestController::class, 'decline'])->name('documents.decline');
    // Admin/creator withdraws a still-open request (removes it from the recipient's To-Sign list).
    Route::post('documents/{documentRequest}/cancel', [\App\Http\Controllers\DocumentRequestController::class, 'cancel'])->name('documents.cancel');

    // Notifications
    Route::get('notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-json', [App\Http\Controllers\NotificationController::class, 'unreadJson'])->name('notifications.unread-json');
    Route::get('notifications/{id}/open', [App\Http\Controllers\NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/{id}/mark-read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    // 6. Onboarding (Employee/Manager view)
    Route::get('onboarding', [EmployeeOnboardingController::class, 'index'])->name('onboarding.index');
    Route::get('onboarding/{onboarding}', [EmployeeOnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding/tasks/{task}/complete', [EmployeeOnboardingController::class, 'completeTask'])->name('onboarding.tasks.complete');

    // 7. Admin-only routes
    Route::middleware(['role:super_admin,hr_admin'])->group(function() {
        Route::post('invitation/{employee}/resend', [\App\Http\Controllers\InvitationController::class, 'resendInvitation'])->name('invitation.resend');
        Route::post('invitation/{employee}/update-email', [\App\Http\Controllers\InvitationController::class, 'updateEmailAndResend'])->name('invitation.update-email');
        Route::post('invitation/{employee}/cancel', [\App\Http\Controllers\InvitationController::class, 'cancelInvitation'])->name('invitation.cancel');

        Route::get('/admin/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('admin.audit-logs');
        // RoleController only implements index; role changes go through
        // EmployeeController::updateRole. Avoid registering dead CRUD routes.
        Route::resource('roles', \App\Http\Controllers\RoleController::class)->only(['index']);

        // Job Locations (central list of where employees work)
        Route::resource('job-locations', \App\Http\Controllers\JobLocationController::class)
            ->except(['show', 'create', 'edit'])
            ->parameters(['job-locations' => 'jobLocation']);


        Route::resource('departments', DepartmentController::class);
        Route::get('departments/{department}/employees', [DepartmentController::class, 'employees'])->name('departments.employees');

        // Company-document signing builder (steps 2–4) — one system, no separate
        // template module. All keyed by the company document itself.
        Route::get('company-documents/{document}/place-fields', [\App\Http\Controllers\CompanyDocumentController::class, 'placeFields'])->name('company-documents.place-fields');
        Route::put('company-documents/{document}/place-fields', [\App\Http\Controllers\CompanyDocumentController::class, 'saveFields'])->name('company-documents.save-fields');
        Route::get('company-documents/{document}/preview-sign', [\App\Http\Controllers\CompanyDocumentController::class, 'previewSign'])->name('company-documents.preview-sign');
        Route::get('company-documents/{document}/signing-file', [\App\Http\Controllers\CompanyDocumentController::class, 'signingFile'])->name('company-documents.signing-file');
        Route::get('company-documents/{document}/send', [\App\Http\Controllers\CompanyDocumentController::class, 'sendForm'])->name('company-documents.send-form');
        Route::post('company-documents/{document}/send', [\App\Http\Controllers\CompanyDocumentController::class, 'send'])->name('company-documents.send');

        // Reusable saved signatures (email signatures) — stamped onto documents.
        Route::get('signature-templates', [\App\Http\Controllers\SignatureTemplateController::class, 'index'])->name('signature-templates.index');
        Route::post('signature-templates', [\App\Http\Controllers\SignatureTemplateController::class, 'store'])->name('signature-templates.store');
        Route::delete('signature-templates/{signatureTemplate}', [\App\Http\Controllers\SignatureTemplateController::class, 'destroy'])->name('signature-templates.destroy');

        // Entities are the company's legal entities — created/edited, not deleted
        // (there is no destroy() and no delete UI). Exclude the broken DELETE route.
        Route::resource('company-entities', CompanyEntityController::class)->except(['destroy']);
        Route::post('company-entities/{entity}/set-primary', [CompanyEntityController::class, 'setPrimary'])->name('company-entities.set-primary');
        
        Route::resource('work-schedules', WorkScheduleController::class);
        Route::post('work-schedules/{work_schedule}/set-default', [WorkScheduleController::class, 'setDefault'])->name('work-schedules.set-default');
        
        // No create/edit pages (calendars are created/edited via modals; the
        // create view doesn't exist and there's no edit() method) — exclude them.
        Route::resource('holiday-calendars', HolidayCalendarController::class)->except(['create', 'edit']);
        Route::post('holiday-calendars/{holiday_calendar}/add-holiday', [HolidayCalendarController::class, 'addHoliday'])->name('holiday-calendars.add-holiday');
        Route::delete('holiday-calendars/{holiday_calendar}/remove-holiday/{holiday}', [HolidayCalendarController::class, 'removeHoliday'])->name('holiday-calendars.remove-holiday');
        Route::post('holiday-calendars/{holiday_calendar}/assign', [HolidayCalendarController::class, 'assign'])->name('holiday-calendars.assign');
        Route::delete('holiday-calendars/{holiday_calendar}/unassign/{user}', [HolidayCalendarController::class, 'unassign'])->name('holiday-calendars.unassign');

        // Time Tracking Policies (how employees log hours; scoped to entities/departments)
        Route::resource('time-tracking-policies', \App\Http\Controllers\TimeTrackingPolicyController::class)
            ->parameters(['time-tracking-policies' => 'timeTrackingPolicy'])
            ->except(['show']);

        // Attendance deviation report (Reports → Attendance)
        Route::get('reports/attendance', [\App\Http\Controllers\ReportsController::class, 'attendance'])->name('reports.attendance');

        // Company Forms — dynamic form builder (admin)
        Route::get('company-forms', [\App\Http\Controllers\CompanyFormController::class, 'index'])->name('company-forms.index');
        Route::get('company-forms/archived', [\App\Http\Controllers\CompanyFormController::class, 'archived'])->name('company-forms.archived');
        Route::post('company-forms', [\App\Http\Controllers\CompanyFormController::class, 'store'])->name('company-forms.store');
        Route::get('company-forms/{companyForm}/builder', [\App\Http\Controllers\CompanyFormController::class, 'builder'])->name('company-forms.builder');
        Route::get('company-forms/{companyForm}/preview', [\App\Http\Controllers\CompanyFormController::class, 'preview'])->name('company-forms.preview');
        Route::put('company-forms/{companyForm}', [\App\Http\Controllers\CompanyFormController::class, 'update'])->name('company-forms.update');
        Route::post('company-forms/{companyForm}/fields', [\App\Http\Controllers\CompanyFormController::class, 'addField'])->name('company-forms.fields.add');
        Route::put('form-fields/{field}', [\App\Http\Controllers\CompanyFormController::class, 'updateField'])->name('company-forms.fields.update');
        Route::delete('form-fields/{field}', [\App\Http\Controllers\CompanyFormController::class, 'deleteField'])->name('company-forms.fields.delete');
        Route::post('form-fields/{field}/move', [\App\Http\Controllers\CompanyFormController::class, 'moveField'])->name('company-forms.fields.move');
        Route::post('company-forms/{companyForm}/assign', [\App\Http\Controllers\CompanyFormController::class, 'assign'])->name('company-forms.assign');
        Route::post('company-forms/{companyForm}/open-month', [\App\Http\Controllers\CompanyFormController::class, 'openMonth'])->name('company-forms.open-month');
        Route::delete('company-forms/{companyForm}/assignments/{assignment}', [\App\Http\Controllers\CompanyFormController::class, 'unassign'])->name('company-forms.unassign');
        Route::get('company-forms/{companyForm}/details', [\App\Http\Controllers\CompanyFormController::class, 'show'])->name('company-forms.show');
        Route::get('company-forms/{companyForm}/export', [\App\Http\Controllers\CompanyFormController::class, 'exportResponses'])->name('company-forms.export');
        // Reviewer access management (super admin only — enforced in controller).
        Route::post('company-forms/{companyForm}/reviewers', [\App\Http\Controllers\CompanyFormController::class, 'assignReviewer'])->name('company-forms.reviewers.add');
        Route::delete('company-forms/{companyForm}/reviewers/{user}', [\App\Http\Controllers\CompanyFormController::class, 'removeReviewer'])->name('company-forms.reviewers.remove');
        Route::delete('company-forms/{companyForm}', [\App\Http\Controllers\CompanyFormController::class, 'destroy'])->name('company-forms.destroy');
        Route::post('company-forms/{companyForm}/restore', [\App\Http\Controllers\CompanyFormController::class, 'restore'])->name('company-forms.restore');
        Route::delete('company-forms/{companyForm}/force', [\App\Http\Controllers\CompanyFormController::class, 'forceDelete'])->name('company-forms.force-delete');

        // Company Policies — admin (create, assign, track acknowledgments)
        Route::get('company-policies', [\App\Http\Controllers\CompanyPolicyController::class, 'index'])->name('company-policies.index');
        Route::get('company-policies/deleted', [\App\Http\Controllers\CompanyPolicyController::class, 'deleted'])->name('company-policies.deleted');
        Route::get('company-policies/create', [\App\Http\Controllers\CompanyPolicyController::class, 'create'])->name('company-policies.create');
        Route::post('company-policies', [\App\Http\Controllers\CompanyPolicyController::class, 'store'])->name('company-policies.store');
        Route::get('company-policies/{companyPolicy}/edit', [\App\Http\Controllers\CompanyPolicyController::class, 'edit'])->name('company-policies.edit');
        Route::put('company-policies/{companyPolicy}', [\App\Http\Controllers\CompanyPolicyController::class, 'update'])->name('company-policies.update');
        Route::get('company-policies/{companyPolicy}', [\App\Http\Controllers\CompanyPolicyController::class, 'show'])->name('company-policies.show');
        Route::post('company-policies/{companyPolicy}/assign', [\App\Http\Controllers\CompanyPolicyController::class, 'assign'])->name('company-policies.assign');
        Route::get('company-policies/{companyPolicy}/acknowledgments', [\App\Http\Controllers\CompanyPolicyController::class, 'acknowledgments'])->name('company-policies.acknowledgments');
        Route::get('company-policies/{companyPolicy}/export', [\App\Http\Controllers\CompanyPolicyController::class, 'export'])->name('company-policies.export');
        Route::post('policy-acknowledgments/{acknowledgment}/remind', [\App\Http\Controllers\CompanyPolicyController::class, 'sendReminder'])->name('company-policies.remind');
        Route::delete('company-policies/{companyPolicy}', [\App\Http\Controllers\CompanyPolicyController::class, 'destroy'])->name('company-policies.destroy');
        Route::post('company-policies/{companyPolicy}/restore', [\App\Http\Controllers\CompanyPolicyController::class, 'restore'])->name('company-policies.restore');
        Route::delete('company-policies/{companyPolicy}/force', [\App\Http\Controllers\CompanyPolicyController::class, 'forceDelete'])->name('company-policies.force-delete');

        // Company Documents — admin file library
        Route::get('company-documents', [\App\Http\Controllers\CompanyDocumentController::class, 'adminIndex'])->name('company-documents.admin');
        Route::get('company-documents/archived', [\App\Http\Controllers\CompanyDocumentController::class, 'archived'])->name('company-documents.archived');
        Route::get('company-documents/create', [\App\Http\Controllers\CompanyDocumentController::class, 'create'])->name('company-documents.create');
        Route::post('company-documents', [\App\Http\Controllers\CompanyDocumentController::class, 'store'])->name('company-documents.store');
        Route::get('company-documents/{document}/acknowledgments', [\App\Http\Controllers\CompanyDocumentController::class, 'acknowledgments'])->name('company-documents.acknowledgments');
        Route::get('company-documents/{document}/signing', [\App\Http\Controllers\CompanyDocumentController::class, 'signing'])->name('company-documents.signing');
        Route::get('company-documents/{document}/edit', [\App\Http\Controllers\CompanyDocumentController::class, 'edit'])->name('company-documents.edit');
        Route::get('company-documents/{document}/edit-content', [\App\Http\Controllers\CompanyDocumentController::class, 'editContent'])->name('company-documents.edit-content');
        Route::post('company-documents/{document}/convert', [\App\Http\Controllers\CompanyDocumentController::class, 'convertToPdf'])->name('company-documents.convert');
        Route::post('company-documents/{document}/convert-original', [\App\Http\Controllers\CompanyDocumentController::class, 'convertOriginal'])->name('company-documents.convert-original');
        Route::put('company-documents/{document}', [\App\Http\Controllers\CompanyDocumentController::class, 'update'])->name('company-documents.update');
        Route::post('company-documents/{document}/new-version', [\App\Http\Controllers\CompanyDocumentController::class, 'newVersion'])->name('company-documents.new-version');
        Route::delete('company-documents/{document}', [\App\Http\Controllers\CompanyDocumentController::class, 'destroy'])->name('company-documents.destroy');
        Route::post('company-documents/{document}/restore', [\App\Http\Controllers\CompanyDocumentController::class, 'restoreDocument'])->name('company-documents.restore');
        Route::delete('company-documents/{document}/force', [\App\Http\Controllers\CompanyDocumentController::class, 'forceDelete'])->name('company-documents.force-delete');
        Route::get('document-categories', [\App\Http\Controllers\DocumentCategoryController::class, 'index'])->name('document-categories.index');
        Route::get('document-categories/manage', [\App\Http\Controllers\DocumentCategoryController::class, 'manage'])->name('document-categories.manage');
        Route::get('document-categories/deleted', [\App\Http\Controllers\DocumentCategoryController::class, 'deleted'])->name('document-categories.deleted');
        Route::post('document-categories', [\App\Http\Controllers\DocumentCategoryController::class, 'store'])->name('document-categories.store');
        Route::put('document-categories/{documentCategory}', [\App\Http\Controllers\DocumentCategoryController::class, 'update'])->name('document-categories.update');
        Route::delete('document-categories/{documentCategory}', [\App\Http\Controllers\DocumentCategoryController::class, 'destroy'])->name('document-categories.destroy');
        Route::post('document-categories/{documentCategory}/restore', [\App\Http\Controllers\DocumentCategoryController::class, 'restore'])->name('document-categories.restore');
        Route::delete('document-categories/{documentCategory}/force', [\App\Http\Controllers\DocumentCategoryController::class, 'forceDelete'])->name('document-categories.force-delete');

        // Automated daily attendance report — settings, manual send, preview
        Route::prefix('attendance-reports')->name('attendance-reports.')->group(function () {
            Route::get('settings', [\App\Http\Controllers\AttendanceReportController::class, 'settings'])->name('settings');
            Route::post('settings', [\App\Http\Controllers\AttendanceReportController::class, 'updateSettings'])->name('settings.update');
            Route::post('send-manual', [\App\Http\Controllers\AttendanceReportController::class, 'sendManual'])->name('send-manual');
            Route::get('preview', [\App\Http\Controllers\AttendanceReportController::class, 'previewReport'])->name('preview');
        });

        // Company Events (admin manage; published events shown to employees)
        Route::get('events/calendar-data', [\App\Http\Controllers\EventController::class, 'calendarData'])->name('events.calendar-data');
        Route::post('events/{event}/publish', [\App\Http\Controllers\EventController::class, 'publish'])->name('events.publish');
        Route::post('events/{event}/unpublish', [\App\Http\Controllers\EventController::class, 'unpublish'])->name('events.unpublish');
        Route::post('events/{event}/toggle-pin', [\App\Http\Controllers\EventController::class, 'togglePin'])->name('events.toggle-pin');
        Route::post('events/{event}/archive', [\App\Http\Controllers\EventController::class, 'archive'])->name('events.archive');
        Route::post('events/{event}/restore', [\App\Http\Controllers\EventController::class, 'restore'])->name('events.restore');
        Route::resource('events', \App\Http\Controllers\EventController::class)->only(['index', 'store', 'update', 'destroy']);

        // All-employees × all-categories balance overview. MUST be before the
        // resource route so "balances-overview" isn't captured as a policy id.
        Route::get('time-off-policies/balances-overview', [TimeOffPolicyController::class, 'balancesOverview'])->name('time-off-policies.balances-overview');
        Route::post('time-off-policies/balances/recompute', [TimeOffPolicyController::class, 'recomputeBalances'])->name('time-off-policies.recompute-balances');
        Route::resource('time-off-policies', TimeOffPolicyController::class);
        Route::post('time-off-policies/{policy}/assign', [TimeOffPolicyController::class, 'assign'])->name('time-off-policies.assign');
        Route::post('time-off-policies/{policy}/unassign', [TimeOffPolicyController::class, 'unassign'])->name('time-off-policies.unassign');
        Route::get('time-off-policies/{time_off_policy}/balances', [TimeOffPolicyController::class, 'balances'])->name('time-off-policies.balances');
        Route::post('time-off-policies/{time_off_policy}/adjust-balance', [TimeOffPolicyController::class, 'adjustBalance'])->name('time-off-policies.adjust-balance');

        // Leave year renewal + encashment (per-company rules).
        Route::resource('leave-year-settings', \App\Http\Controllers\LeaveYearSettingsController::class)
            ->parameters(['leave-year-settings' => 'leaveYearSetting'])
            ->except(['show']);
        Route::get('leave-year-settings/{leaveYearSetting}/preview', [\App\Http\Controllers\LeaveYearSettingsController::class, 'previewRenewal'])->name('leave-year-settings.preview');
        Route::post('leave-year-settings/{leaveYearSetting}/renew', [\App\Http\Controllers\LeaveYearSettingsController::class, 'manualRenewal'])->name('leave-year-settings.renew');
        Route::get('leave-encashments', [\App\Http\Controllers\LeaveEncashmentController::class, 'index'])->name('leave-encashments.index');
        Route::get('leave-encashments/export', [\App\Http\Controllers\LeaveEncashmentController::class, 'export'])->name('leave-encashments.export');
        Route::get('leave-encashments/export-pdf', [\App\Http\Controllers\LeaveEncashmentController::class, 'exportPdf'])->name('leave-encashments.export-pdf');
        Route::post('leave-encashments/mark-paid', [\App\Http\Controllers\LeaveEncashmentController::class, 'markPaid'])->name('leave-encashments.mark-paid');
        Route::post('leave-encashments/{record}/approve', [\App\Http\Controllers\LeaveEncashmentController::class, 'approve'])->name('leave-encashments.approve');
        Route::post('leave-encashments/{record}/reject', [\App\Http\Controllers\LeaveEncashmentController::class, 'reject'])->name('leave-encashments.reject');
        
        // Onboarding Admin Actions
        Route::resource('onboarding-workflows', OnboardingWorkflowController::class)->names('onboarding.workflows');
        Route::post('onboarding-workflows/{onboarding_workflow}/tasks', [OnboardingWorkflowController::class, 'storeTask'])->name('onboarding.workflows.tasks.store');
        Route::delete('onboarding-workflows/{onboarding_workflow}/tasks/{task}', [OnboardingWorkflowController::class, 'destroyTask'])->name('onboarding.workflows.tasks.destroy');
        
        Route::post('onboarding/start', [EmployeeOnboardingController::class, 'start'])->name('onboarding.start');
        Route::post('onboarding/tasks/{task}/skip', [EmployeeOnboardingController::class, 'skipTask'])->name('onboarding.tasks.skip');
    });

    // 8. Attendance Module
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('office-status', [AttendanceController::class, 'officeStatus'])->name('office-status');
        Route::post('clock-in', [AttendanceController::class, 'clockIn'])->name('clock-in');
        Route::post('clock-out', [AttendanceController::class, 'clockOut'])->name('clock-out');
        Route::post('break/start', [AttendanceController::class, 'startBreak'])->name('break.start');
        Route::post('break/end', [AttendanceController::class, 'endBreak'])->name('break.end');
        Route::get('today', [AttendanceController::class, 'todayStatus'])->name('today');
        Route::get('my-history', [AttendanceController::class, 'myHistory'])->name('my-history');
        Route::post('correction', [AttendanceController::class, 'submitCorrection'])->name('correction.submit');

        // Manager + Admin routes
        Route::middleware(['role:manager,hr_admin,super_admin'])->group(function () {
            Route::get('live', [AttendanceManagerController::class, 'liveBoard'])->name('live');
            Route::get('on-leave', [AttendanceManagerController::class, 'onLeave'])->name('on-leave');
            Route::get('team', [AttendanceManagerController::class, 'teamHistory'])->name('team');
            Route::get('team/export', [AttendanceManagerController::class, 'teamHistoryExport'])->name('team.export');
            Route::get('corrections', [AttendanceManagerController::class, 'pendingCorrections'])->name('corrections');
            Route::post('corrections/{correction}/approve', [AttendanceManagerController::class, 'approveCorrection'])->name('corrections.approve');
            Route::post('corrections/{correction}/reject', [AttendanceManagerController::class, 'rejectCorrection'])->name('corrections.reject');
        });

        // HR Admin + Super Admin only
        Route::middleware(['role:hr_admin,super_admin'])->group(function () {
            Route::post('manual', [AttendanceManagerController::class, 'manualEntry'])->name('manual');
            Route::put('records/{record}/times', [AttendanceManagerController::class, 'updateTimes'])->name('records.update-times');
            Route::post('recalc-late', [AttendanceManagerController::class, 'recalcLate'])->name('recalc-late');
            Route::post('employee/{employee}/entry', [AttendanceManagerController::class, 'profileAttendance'])->name('employee-entry');
            Route::post('employee/{employee}/recalc', [AttendanceManagerController::class, 'recalcEmployee'])->name('employee-recalc');
            Route::get('backfill', [AttendanceManagerController::class, 'backfillForm'])->name('backfill');
            Route::post('backfill', [AttendanceManagerController::class, 'backfill'])->name('backfill.store');
        });
    });

    // Employee personal routes
    Route::get('my-schedule', [\App\Http\Controllers\PageController::class, 'mySchedule'])->name('shifts.my-schedule');

    // Office Locations & Shifts (Admin Only)
    Route::middleware(['role:hr_admin,super_admin'])->group(function () {
        Route::resource('shifts', ShiftController::class)->except(['create', 'edit', 'show']);
        Route::get('shifts/{shift}/employees', [ShiftController::class, 'employees'])->name('shifts.employees');
        Route::delete('shifts/{shift}/employees/{employee}', [ShiftController::class, 'unassignEmployee'])->name('shifts.unassign-employee');
        Route::post('shifts/{shift}/unassign-all', [ShiftController::class, 'unassignAll'])->name('shifts.unassign-all');
        Route::post('shifts/{shift}/set-default', [ShiftController::class, 'setDefault'])->name('shifts.set-default');
        Route::post('shifts/{shift}/assign-all', [ShiftController::class, 'assignToAll'])->name('shifts.assign-all');
        Route::post('shifts/{shift}/assign-selected', [ShiftController::class, 'assignToSelected'])->name('shifts.assign-selected');
        
        Route::post('employees/{employee}/shifts/assign-single', [ShiftAssignmentController::class, 'assignSingle'])->name('employees.shifts.assign.single');
        Route::post('employees/{employee}/shifts/assign-recurring', [ShiftAssignmentController::class, 'assignRecurring'])->name('employees.shifts.assign.recurring');
        
        Route::resource('office-locations', OfficeLocationController::class);
        Route::get('office-locations-assign', [OfficeLocationController::class, 'assignView'])->name('office-locations.assignView');
        Route::post('office-locations/assign', [OfficeLocationController::class, 'assign'])->name('office-locations.assign');
        Route::post('office-locations/unassign', [OfficeLocationController::class, 'unassign'])->name('office-locations.unassign');

        // ZKTeco Device Integration
        Route::prefix('zkteco')->name('zkteco.')->group(function() {
            Route::get('/', [\App\Http\Controllers\ZktecoController::class, 'dashboard'])->name('dashboard');
            Route::get('devices', [\App\Http\Controllers\ZktecoController::class, 'devices'])->name('devices');
            Route::post('devices', [\App\Http\Controllers\ZktecoController::class, 'storeDevice'])->name('devices.store');
            Route::put('devices/{device}', [\App\Http\Controllers\ZktecoController::class, 'updateDevice'])->name('devices.update');
            Route::delete('devices/{device}', [\App\Http\Controllers\ZktecoController::class, 'destroyDevice'])->name('devices.destroy');
            Route::post('devices/{device}/test', [\App\Http\Controllers\ZktecoController::class, 'testConnection'])->name('devices.test');
            Route::post('devices/{device}/sync', [\App\Http\Controllers\ZktecoController::class, 'syncNow'])->name('devices.sync');
            Route::get('unmapped', [\App\Http\Controllers\ZktecoController::class, 'unmapped'])->name('unmapped');
            Route::post('unmapped/resolve', [\App\Http\Controllers\ZktecoController::class, 'resolveMapping'])->name('unmapped.resolve');
            Route::get('import', [\App\Http\Controllers\ZktecoController::class, 'showImport'])->name('import');
            Route::post('import', [\App\Http\Controllers\ZktecoController::class, 'import'])->name('import.store');
            Route::post('clear-data', [\App\Http\Controllers\ZktecoController::class, 'clearData'])->name('clear-data')->middleware('role:super_admin');
            Route::post('rebuild-attendance', [\App\Http\Controllers\ZktecoController::class, 'rebuildAttendance'])->name('rebuild-attendance')->middleware('role:super_admin');
        });
    });
});

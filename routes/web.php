<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

Route::get('/login',[AuthController::class,'loginForm'])->name('login');
Route::post('/login',[AuthController::class,'login'])->middleware('throttle:5,1');
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');

// Halaman Kiosk Display QR Presensi (Publik / Tanpa Login)
Route::get('/kiosk/qr/code/{code}', [KioskController::class, 'code'])->middleware('throttle:60,1')->name('kiosk.qr.code');
Route::get('/kiosk/qr/{code?}', [KioskController::class, 'show'])->name('kiosk.qr');

Route::middleware(['auth','active','must.change.password'])->group(function () {
    Route::get('/',[DashboardController::class,'index'])->name('home');
    Route::get('/password/force-change', [AuthController::class, 'forceChangePasswordView'])->name('password.force_change');
    Route::post('/password/force-change', [AuthController::class, 'forceChangePasswordUpdate'])->name('password.force_change.update');
    Route::get('/people',[AdminController::class,'people'])->name('people');
    Route::get('/people/create',[AdminController::class,'personCreate'])->name('people.create');
    Route::post('/people',[AdminController::class,'person'])->name('people.store');
    Route::post('/people/bulk-reset-password',[AdminController::class,'peopleBulkResetPassword'])->name('people.bulk_reset_password');
    Route::post('/people/bulk-generate-username',[AdminController::class,'peopleBulkGenerateUsername'])->name('people.bulk_generate_username');
    Route::get('/people/{id}',[AdminController::class,'personShow'])->name('people.show');
    Route::get('/people/{id}/edit',[AdminController::class,'personEdit'])->name('people.edit');
    Route::post('/people/{id}',[AdminController::class,'personUpdate'])->name('people.update');
    Route::post('/people/{id}/reset-password',[AdminController::class,'personResetPassword'])->name('people.reset_password');
    Route::post('/people/{id}/generate-username',[AdminController::class,'personGenerateUsername'])->name('people.generate_username');
    Route::post('/people/{id}/toggle-status',[AdminController::class,'personToggleStatus'])->name('people.toggle_status');
    Route::post('/people/{id}/delete',[AdminController::class,'personDestroy'])->name('people.destroy');
    Route::delete('/people/{id}',[AdminController::class,'personDestroy']);
    Route::post('/password/update', [AuthController::class, 'updatePassword'])->name('password.update');

    Route::get('/branches', [AdminController::class, 'branches'])->name('branches.index');
    Route::get('/branches/create', [AdminController::class, 'branchCreate'])->name('branches.create');
    Route::post('/branches', [AdminController::class, 'branch'])->name('branches.store');
    Route::get('/branches/{id}', [AdminController::class, 'branchShow'])->name('branches.show');
    Route::get('/branches/{id}/edit', [AdminController::class, 'branchEdit'])->name('branches.edit');
    Route::post('/branches/{id}', [AdminController::class, 'branchUpdate'])->name('branches.update');
    Route::post('/branches/{id}/delete', [AdminController::class, 'branchDestroy'])->name('branches.destroy');
    Route::delete('/branches/{id}', [AdminController::class, 'branchDestroy']);

    Route::get('/positions', [AdminController::class, 'positions'])->name('positions.index');
    Route::get('/positions/create', [AdminController::class, 'positionCreate'])->name('positions.create');
    Route::post('/positions', [AdminController::class, 'position'])->name('positions.store');
    Route::get('/positions/{id}', [AdminController::class, 'positionShow'])->name('positions.show');
    Route::get('/positions/{id}/edit', [AdminController::class, 'positionEdit'])->name('positions.edit');
    Route::post('/positions/{id}', [AdminController::class, 'positionUpdate'])->name('positions.update');
    Route::post('/positions/{id}/delete', [AdminController::class, 'positionDestroy'])->name('positions.destroy');
    Route::delete('/positions/{id}', [AdminController::class, 'positionDestroy']);

    Route::get('/import',[AdminController::class,'importForm'])->name('import');
    Route::post('/import/preview',[AdminController::class,'importPreview'])->name('import.preview');
    Route::post('/import/commit',[AdminController::class,'importCommit'])->name('import.commit');
    Route::get('/shifts', [AdminController::class, 'shifts'])->name('shifts');
    Route::get('/shifts/calendar', [AdminController::class, 'shiftsCalendar'])->name('shifts.calendar');
    Route::get('/shifts/exchanges', [AdminController::class, 'shiftExchanges'])->name('shifts.exchanges');
    Route::post('/shifts/exchanges/open-slot', [AdminController::class, 'shiftExchangeOpenSlotStore'])->name('shifts.exchanges.open-slot');
    Route::post('/shifts/exchanges/{id}/peer-respond', [AdminController::class, 'shiftExchangePeerRespond'])->name('shifts.exchanges.peer-respond');
    Route::post('/shifts/exchanges/{id}/review', [AdminController::class, 'shiftExchangeReview'])->name('shifts.exchanges.review');
    Route::post('/shifts/{id}/exchange', [AdminController::class, 'shiftExchangeStore'])->name('shifts.exchange.store');
    Route::post('/shifts/{id}/reassign', [AdminController::class, 'shiftDirectReassign'])->name('shifts.reassign');
    Route::get('/shifts/create', [AdminController::class, 'shiftCreate'])->name('shifts.create');
    Route::post('/shifts', [AdminController::class, 'shift'])->name('shifts.store');
    Route::get('/shifts/{id}', [AdminController::class, 'shiftShow'])->name('shifts.show');
    Route::get('/shifts/{id}/edit', [AdminController::class, 'shiftEdit'])->name('shifts.edit');
    Route::post('/shifts/{id}/update', [AdminController::class, 'shiftUpdate'])->name('shifts.update');
    Route::post('/shifts/{id}/approve', [AdminController::class, 'shiftApprove'])->name('shifts.approve');
    Route::post('/shifts/{id}/attendance-manual', [AdminController::class, 'manualAttendance'])->name('shifts.attendance.manual');
    Route::post('/shifts/{id}/delete', [AdminController::class, 'shiftDestroy'])->name('shifts.destroy');
    Route::delete('/shifts/{id}', [AdminController::class, 'shiftDestroy']);
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::get('/audit', [AuditController::class, 'index'])->name('audit');
    Route::post('/settings', [AdminController::class, 'settingsSave'])->name('settings.save');
    Route::get('/attendance/today', [AttendanceController::class, 'todayPage'])->name('attendance.today');
    Route::post('/attendance/{shiftId}', [AttendanceController::class, 'act'])->middleware('throttle:10,1')->name('attendance.act');
    Route::post('/attendance/{shiftId}/exception', [AttendanceController::class, 'exception'])->name('attendance.exception');
    Route::get('/branch-qr', [AttendanceController::class, 'qrPage'])->name('qr.page');
    Route::get('/branch-qr/code', [AttendanceController::class, 'qr'])->name('qr.code');
    Route::get('/attendance-review', [AttendanceController::class, 'review'])->name('attendance.review');
    Route::post('/attendance-exceptions/{id}', [AttendanceController::class, 'reviewException'])->name('attendance.exceptions.review');
    Route::post('/attendance-records/{id}/correct', [AttendanceController::class, 'correction'])->name('attendance.correct');
    Route::post('/attendance-records/{id}/overtime', [AttendanceController::class, 'overtime'])->name('attendance.overtime');
    Route::delete('/attendance-records/{id}', [AdminController::class, 'attendanceDestroy'])->name('attendance.destroy');
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave');
    Route::get('/leave/create', [LeaveController::class, 'create'])->name('leave.create');
    Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::get('/leave/{id}', [LeaveController::class, 'show'])->name('leave.show');
    Route::post('/leave/{id}/review', [LeaveController::class, 'review'])->name('leave.review');
    Route::post('/leave/{id}/delete', [LeaveController::class, 'destroy'])->name('leave.destroy');
    Route::delete('/leave/{id}', [LeaveController::class, 'destroy']);
    Route::get('/leave/{id}/certificate', [LeaveController::class, 'certificate'])->name('leave.certificate');
    Route::get('/payroll',[PayrollController::class,'index'])->name('payroll.index');
    Route::get('/payroll/slip/{userId}', [PayrollController::class, 'slip'])->name('payroll.slip');
    Route::post('/payroll/generate',[PayrollController::class,'generate'])->name('payroll.generate');
    Route::post('/payroll/approve',[PayrollController::class,'approve'])->name('payroll.approve');
    Route::post('/payroll/lock',[PayrollController::class,'lock'])->name('payroll.lock');
    Route::post('/payroll/adjustment',[PayrollController::class,'adjustment'])->name('payroll.adjustment');
    Route::post('/payroll/reset',[PayrollController::class,'reset'])->name('payroll.reset');
    Route::get('/payroll/export',[PayrollController::class,'export'])->name('payroll.export');

    // Onboarding Digital (HR Management)
    Route::get('/onboarding', [\App\Http\Controllers\OnboardingController::class, 'index'])->name('onboarding.index');
    Route::get('/onboarding/create', [\App\Http\Controllers\OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding', [\App\Http\Controllers\OnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('/onboarding/{id}', [\App\Http\Controllers\OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/{id}/approve-offer', [\App\Http\Controllers\OnboardingController::class, 'approveOffer'])->name('onboarding.approve_offer');
    Route::post('/onboarding/{id}/request-revision', [\App\Http\Controllers\OnboardingController::class, 'requestRevision'])->name('onboarding.request_revision');
    Route::post('/onboarding/{id}/verify-final', [\App\Http\Controllers\OnboardingController::class, 'verifyFinal'])->name('onboarding.verify_final');
    Route::post('/onboarding/{id}/cancel', [\App\Http\Controllers\OnboardingController::class, 'cancel'])->name('onboarding.cancel');
    Route::post('/onboarding/{id}/resend-reminder', [\App\Http\Controllers\OnboardingController::class, 'resendReminder'])->name('onboarding.resend_reminder');
    Route::get('/onboarding/{id}/file/{type}', [\App\Http\Controllers\OnboardingController::class, 'streamFile'])->name('onboarding.stream_file');

    // Bagan Organisasi (Tree Hierarchy & Drag-and-Drop)
    Route::get('/organization-tree', [\App\Http\Controllers\OrgChartController::class, 'index'])->name('org-chart.index');
    Route::get('/api/organization-tree/data', [\App\Http\Controllers\OrgChartController::class, 'treeData'])->name('org-chart.data');
    Route::post('/organization-tree/reassign', [\App\Http\Controllers\OrgChartController::class, 'reassign'])->name('org-chart.reassign');
    Route::get('/organization-tree/history/{userId}', [\App\Http\Controllers\OrgChartController::class, 'history'])->name('org-chart.history');

    // Master Settings: Dokumen & Tanda Tangan
    Route::get('/settings/documents', [\App\Http\Controllers\MasterSettingsDocumentController::class, 'index'])->name('master-settings.documents.index');
    Route::get('/settings/documents/e-sign', [\App\Http\Controllers\MasterSettingsDocumentController::class, 'esignSettings'])->name('master-settings.documents.esign');
    Route::post('/settings/documents/e-sign', [\App\Http\Controllers\MasterSettingsDocumentController::class, 'saveEsignSettings'])->name('master-settings.documents.esign.save');
    Route::post('/settings/documents/preview', [\App\Http\Controllers\MasterSettingsDocumentController::class, 'preview'])->name('master-settings.documents.preview');
    Route::get('/settings/documents/{type}/edit', [\App\Http\Controllers\MasterSettingsDocumentController::class, 'edit'])->name('master-settings.documents.edit');
    Route::post('/settings/documents/{type}/draft', [\App\Http\Controllers\MasterSettingsDocumentController::class, 'saveDraft'])->name('master-settings.documents.draft');
    Route::post('/settings/documents/versions/{versionId}/submit-review', [\App\Http\Controllers\MasterSettingsDocumentController::class, 'submitReview'])->name('master-settings.documents.submit_review');
    Route::post('/settings/documents/versions/{versionId}/approve', [\App\Http\Controllers\MasterSettingsDocumentController::class, 'approve'])->name('master-settings.documents.approve');
    Route::post('/settings/documents/versions/{versionId}/publish', [\App\Http\Controllers\MasterSettingsDocumentController::class, 'publish'])->name('master-settings.documents.publish');
    Route::post('/settings/documents/versions/{versionId}/rollback', [\App\Http\Controllers\MasterSettingsDocumentController::class, 'rollback'])->name('master-settings.documents.rollback');

    // Kontrak Kerja
    Route::get('/contracts', [\App\Http\Controllers\EmploymentContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create/{userId}', [\App\Http\Controllers\EmploymentContractController::class, 'create'])->name('contracts.create');
    Route::post('/contracts', [\App\Http\Controllers\EmploymentContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{id}', [\App\Http\Controllers\EmploymentContractController::class, 'show'])->name('contracts.show');
    Route::post('/contracts/{id}/send-esign', [\App\Http\Controllers\EmploymentContractController::class, 'sendEsign'])->name('contracts.send_esign');
});

// Portal Onboarding Publik / Token Akses Aman Kandidat (Tanpa Login Akun)
Route::prefix('onboarding/portal')->group(function () {
    Route::get('/{token}', [\App\Http\Controllers\CandidatePortalController::class, 'portal'])->name('onboarding.portal');
    Route::post('/{token}/sign-offer', [\App\Http\Controllers\CandidatePortalController::class, 'signOffer'])->middleware('throttle:15,1')->name('onboarding.portal.sign_offer');
    Route::post('/{token}/sign-nda', [\App\Http\Controllers\CandidatePortalController::class, 'signNda'])->middleware('throttle:15,1')->name('onboarding.portal.sign_nda');
    Route::post('/{token}/upload-ktp', [\App\Http\Controllers\CandidatePortalController::class, 'submitKtp'])->middleware('throttle:15,1')->name('onboarding.portal.upload_ktp');
    Route::post('/{token}/submit-liveness', [\App\Http\Controllers\CandidatePortalController::class, 'submitLiveness'])->middleware('throttle:15,1')->name('onboarding.portal.submit_liveness');
});

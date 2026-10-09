<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

Route::get('/login',[AuthController::class,'loginForm'])->name('login');
Route::post('/login',[AuthController::class,'login'])->middleware('throttle:5,1');
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth','active'])->group(function () {
    Route::get('/',[DashboardController::class,'index'])->name('home');
    Route::get('/people',[AdminController::class,'people'])->name('people');
    Route::get('/people/create',[AdminController::class,'personCreate'])->name('people.create');
    Route::post('/people',[AdminController::class,'person'])->name('people.store');
    Route::get('/people/{id}',[AdminController::class,'personShow'])->name('people.show');
    Route::get('/people/{id}/edit',[AdminController::class,'personEdit'])->name('people.edit');
    Route::post('/people/{id}',[AdminController::class,'personUpdate'])->name('people.update');
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
    Route::get('/shifts/create', [AdminController::class, 'shiftCreate'])->name('shifts.create');
    Route::post('/shifts', [AdminController::class, 'shift'])->name('shifts.store');
    Route::get('/shifts/{id}', [AdminController::class, 'shiftShow'])->name('shifts.show');
    Route::get('/shifts/{id}/edit', [AdminController::class, 'shiftEdit'])->name('shifts.edit');
    Route::post('/shifts/{id}/update', [AdminController::class, 'shiftUpdate'])->name('shifts.update');
    Route::post('/shifts/{id}/approve', [AdminController::class, 'shiftApprove'])->name('shifts.approve');
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
});

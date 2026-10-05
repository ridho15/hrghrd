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

Route::middleware(['auth','active'])->group(function () {
    Route::get('/',[DashboardController::class,'index'])->name('home');
    Route::post('/logout',[AuthController::class,'logout'])->name('logout');
    Route::get('/people',[AdminController::class,'people'])->name('people');
    Route::post('/branches',[AdminController::class,'branch'])->name('branches.store');
    Route::post('/branches/{id}',[AdminController::class,'branchUpdate'])->name('branches.update');
    Route::post('/people',[AdminController::class,'person'])->name('people.store');
    Route::post('/positions',[AdminController::class,'position'])->name('positions.store');
    Route::post('/people/{id}',[AdminController::class,'personUpdate'])->name('people.update');
    Route::get('/import',[AdminController::class,'importForm'])->name('import');
    Route::post('/import/preview',[AdminController::class,'importPreview'])->name('import.preview');
    Route::post('/import/commit',[AdminController::class,'importCommit'])->name('import.commit');
    Route::get('/shifts',[AdminController::class,'shifts'])->name('shifts');
    Route::post('/shifts',[AdminController::class,'shift'])->name('shifts.store');
    Route::post('/shifts/{id}/update',[AdminController::class,'shiftUpdate'])->name('shifts.update');
    Route::post('/shifts/{id}/approve',[AdminController::class,'shiftApprove'])->name('shifts.approve');
    Route::get('/settings',[AdminController::class,'settings'])->name('settings');
    Route::get('/audit',[AuditController::class,'index'])->name('audit');
    Route::post('/settings',[AdminController::class,'settingsSave'])->name('settings.save');
    Route::post('/attendance/{shiftId}',[AttendanceController::class,'act'])->middleware('throttle:10,1')->name('attendance.act');
    Route::post('/attendance/{shiftId}/exception',[AttendanceController::class,'exception'])->name('attendance.exception');
    Route::get('/branch-qr',[AttendanceController::class,'qrPage'])->name('qr.page');
    Route::get('/branch-qr/code',[AttendanceController::class,'qr'])->name('qr.code');
    Route::get('/attendance-review',[AttendanceController::class,'review'])->name('attendance.review');
    Route::post('/attendance-exceptions/{id}',[AttendanceController::class,'reviewException'])->name('attendance.exceptions.review');
    Route::post('/attendance-records/{id}/correct',[AttendanceController::class,'correction'])->name('attendance.correct');
    Route::post('/attendance-records/{id}/overtime',[AttendanceController::class,'overtime'])->name('attendance.overtime');
    Route::get('/leave',[LeaveController::class,'index'])->name('leave');
    Route::post('/leave',[LeaveController::class,'store'])->name('leave.store');
    Route::post('/leave/{id}/review',[LeaveController::class,'review'])->name('leave.review');
    Route::get('/leave/{id}/certificate',[LeaveController::class,'certificate'])->name('leave.certificate');
    Route::get('/payroll',[PayrollController::class,'index'])->name('payroll.index');
    Route::post('/payroll/generate',[PayrollController::class,'generate'])->name('payroll.generate');
    Route::post('/payroll/approve',[PayrollController::class,'approve'])->name('payroll.approve');
    Route::post('/payroll/lock',[PayrollController::class,'lock'])->name('payroll.lock');
    Route::post('/payroll/adjustment',[PayrollController::class,'adjustment'])->name('payroll.adjustment');
    Route::get('/payroll/export',[PayrollController::class,'export'])->name('payroll.export');
});

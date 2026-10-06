<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\AttendanceApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\LeaveApiController;
use App\Http\Controllers\Api\PayrollApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\ShiftApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile REST API Routes (HR Group Enterprise)
|--------------------------------------------------------------------------
| Prefix: /api/v1 (didefinisikan di bootstrap/app.php)
| Keamanan: Bearer Token via Laravel Sanctum
*/

// --- Public Auth Route ---
Route::post('/auth/login', [AuthApiController::class, 'login'])
    ->middleware('throttle:10,1');

// --- Protected Routes (Sanctum + Active User) ---
Route::middleware(['auth:sanctum', 'active'])->group(function () {

    // 🔐 Auth Management
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthApiController::class, 'logout']);
        Route::get('/me', [AuthApiController::class, 'me']);
        Route::post('/device', [AuthApiController::class, 'registerDevice']);
        Route::post('/refresh', [AuthApiController::class, 'refresh']);
    });

    // 👤 Profile Karyawan
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileApiController::class, 'profile']);
        Route::get('/today', [ProfileApiController::class, 'today']);
    });

    // 📍 Presensi & QR
    Route::prefix('attendance')->group(function () {
        Route::post('/checkin', [AttendanceApiController::class, 'checkIn'])->middleware('throttle:20,1');
        Route::post('/checkout', [AttendanceApiController::class, 'checkOut'])->middleware('throttle:20,1');
        Route::get('/history', [AttendanceApiController::class, 'history']);
        Route::get('/qr', [AttendanceApiController::class, 'qrCode']);
        Route::post('/{id}/exception', [AttendanceApiController::class, 'exception']);
    });

    // 📅 Jadwal Shift
    Route::prefix('shifts')->group(function () {
        Route::get('/', [ShiftApiController::class, 'index']);
        Route::get('/{id}', [ShiftApiController::class, 'show']);
        Route::post('/', [ShiftApiController::class, 'store']);
        Route::match(['put', 'patch'], '/{id}', [ShiftApiController::class, 'update']);
        Route::post('/{id}/approve', [ShiftApiController::class, 'approve']);
        Route::delete('/{id}', [ShiftApiController::class, 'destroy']);
    });

    // 🏖️ Pengajuan Izin & Cuti
    Route::prefix('leave')->group(function () {
        Route::get('/', [LeaveApiController::class, 'index']);
        Route::post('/', [LeaveApiController::class, 'store']);
        Route::get('/{id}', [LeaveApiController::class, 'show']);
        Route::post('/{id}/review', [LeaveApiController::class, 'review']);
        Route::delete('/{id}', [LeaveApiController::class, 'destroy']);
    });

    // 💰 Slip Gaji & Payroll
    Route::prefix('payroll')->group(function () {
        Route::get('/slip', [PayrollApiController::class, 'mySlip']);
        Route::get('/slip/{userId}', [PayrollApiController::class, 'slip']);
        Route::get('/summary', [PayrollApiController::class, 'summary']);
    });

    // 🏢 Master Data & Administrasi
    Route::prefix('admin')->group(function () {
        Route::get('/employees', [AdminApiController::class, 'employees']);
        Route::get('/branches', [AdminApiController::class, 'branches']);
    });
});

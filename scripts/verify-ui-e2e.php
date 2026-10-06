<?php

/**
 * Script Verifikasi UI/UX End-to-End & Zero-Regression
 * Menguji perenderan seluruh 12 template Blade dengan session otentikasi nyata.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "========================================================\n";
echo "HR GROUP — END-TO-END UI & BLADE RENDERING VERIFICATION\n";
echo "========================================================\n\n";

$admin = User::where('email', 'admin@example.test')->first();
$manager = User::where('email', 'manager@example.test')->first();
$employee = User::where('email', 'karyawan@example.test')->first();

if (!$admin || !$manager || !$employee) {
    echo "❌ ERROR: Pengguna demo (admin, manager, karyawan) belum ter-seed.\n";
    exit(1);
}

function testRoute(string $uri, ?User $user = null, string $label = '', array $expectedSelectors = []): bool {
    echo "Testing: [{$uri}] as " . ($user ? "{$user->role} ({$user->name})" : "Guest") . "... ";
    
    Auth::logout();
    if ($user) {
        Auth::login($user);
    }

    $request = Request::create($uri, 'GET');
    $request->headers->set('Accept', 'text/html');
    
    try {
        $response = app()->handle($request);
        $status = $response->getStatusCode();
        
        if ($status !== 200) {
            echo "FAILED (HTTP {$status})\n";
            return false;
        }

        $content = $response->getContent();

        // Check expected selectors or elements in rendered HTML
        foreach ($expectedSelectors as $selector) {
            if (!str_contains($content, $selector)) {
                echo "FAILED (Missing selector: '{$selector}')\n";
                return false;
            }
        }

        echo "PASSED (HTTP 200, " . strlen($content) . " bytes)\n";
        return true;
    } catch (\Throwable $e) {
        echo "EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        return false;
    }
}

$allPassed = true;

// 1. Tampilan Tamu / Login
echo "--- 1. MODUL OTENTIKASI (GUEST) ---\n";
$allPassed = testRoute('/login', null, 'Login Page', [
    'Portal Karyawan & HR',
    'name="email"',
    'name="password"',
    'Masuk ke Akun'
]) && $allPassed;

// 2. Tampilan Karyawan
echo "\n--- 2. MODUL KARYAWAN (EMPLOYEE) ---\n";
$allPassed = testRoute('/', $employee, 'Dashboard Karyawan', [
    'Halo,',
    'Shift Terdekat',
    'app-sidebar',
    'open-mobile-sidebar'
]) && $allPassed;

$allPassed = testRoute('/leave', $employee, 'Pengajuan Cuti Karyawan', [
    'id="leave-type"',
    'name="certificate"',
    'Formulir Pengajuan'
]) && $allPassed;

// 3. Tampilan Manager
echo "\n--- 3. MODUL MANAJER (MANAGER) ---\n";
$allPassed = testRoute('/shifts', $manager, 'Jadwal Shift', [
    'Jadwal Shift Kerja',
    'Daftar Jadwal Shift'
]) && $allPassed;

$allPassed = testRoute('/attendance-review', $manager, 'Tinjau Presensi', [
    'Tinjau & Koreksi Presensi',
    'Pengecualian Menunggu Keputusan'
]) && $allPassed;

$allPassed = testRoute('/branch-qr', $manager, 'Kiosk QR Cabang', [
    'data-qr-url',
    'qr-code',
    'qr-timer'
]) && $allPassed;

// 4. Tampilan Super Admin
echo "\n--- 4. MODUL SUPER ADMIN (ADMIN) ---\n";
$allPassed = testRoute('/people', $admin, 'Direktori Karyawan & Cabang', [
    'Tambah Cabang Baru',
    'Tambah Akun Karyawan',
    'Direktori Karyawan Terdaftar',
    'reset_device'
]) && $allPassed;

$allPassed = testRoute('/import', $admin, 'Wizard Impor Karyawan', [
    'Impor Data Karyawan',
    'Pilih Berkas Spreadsheet',
    'accept=".csv,.xlsx"'
]) && $allPassed;

$allPassed = testRoute('/payroll', $admin, 'Dashboard Payroll', [
    'Kalkulasi & Rekap Payroll',
    'Simpan Draf Payroll',
    'Slip Gaji Cabang'
]) && $allPassed;

$allPassed = testRoute('/settings', $admin, 'Aturan Kebijakan', [
    'Parameter & Kebijakan HR',
    'late_grace_minutes',
    'daily_divisor',
    'hourly_divisor'
]) && $allPassed;

$allPassed = testRoute('/audit', $admin, 'Jejak Audit Aktivitas', [
    'Jejak Audit Aktivitas',
    'Log Riwayat Peristiwa Terkini'
]) && $allPassed;

echo "\n========================================================\n";
if ($allPassed) {
    echo "🎉 ALL 12 BLADE VIEWS & E2E FLOWS RENDERED SUCCESSFULLY!\n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED. CHECK LOGS ABOVE.\n";
    echo "========================================================\n";
    exit(1);
}

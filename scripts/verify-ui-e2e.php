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
            $msg = isset($response->exception) && $response->exception ? $response->exception->getMessage() : '';
            echo "FAILED (HTTP {$status}) {$msg}\n";
            if (isset($response->exception) && $response->exception) {
                echo "  Trace: " . $response->exception->getFile() . ":" . $response->exception->getLine() . "\n";
            }
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

$allPassed = testRoute('/leave', $employee, 'Daftar Izin Karyawan Bersih', [
    'Pengajuan Izin & Sakit',
    '+ Ajukan Izin / Sakit',
    'Tindakan'
]) && $allPassed;

$allPassed = testRoute('/leave/create', $employee, 'Halaman Formulir Izin Mandiri', [
    'Formulir Pengajuan Izin & Sakit',
    'leave-start-date',
    'leave-end-date',
    'leave-reason'
]) && $allPassed;

$firstLeave = \App\Models\LeaveRequest::first();
if (!$firstLeave) {
    $firstLeave = \App\Models\LeaveRequest::create([
        'user_id' => $employee->id,
        'created_by' => $employee->id,
        'type' => 'leave',
        'start_date' => now('Asia/Jakarta')->addDays(5)->toDateString(),
        'end_date' => now('Asia/Jakarta')->addDays(6)->toDateString(),
        'reason' => 'Keperluan keluarga di luar kota selama dua hari.',
        'status' => 'pending',
    ]);
    $firstLeave->days()->create([
        'date' => now('Asia/Jakarta')->addDays(5)->toDateString(),
        'status' => 'pending',
        'paid' => false,
    ]);
    $firstLeave->days()->create([
        'date' => now('Asia/Jakarta')->addDays(6)->toDateString(),
        'status' => 'pending',
        'paid' => false,
    ]);
}

$allPassed = testRoute('/leave/' . $firstLeave->id, $employee, 'Halaman Dossier Pengajuan Izin Mandiri (Employee)', [
    'Daftar Pengajuan',
    'Alasan Permohonan',
    'Matriks Status per Hari'
]) && $allPassed;

$allPassed = testRoute('/leave/' . $firstLeave->id, $manager, 'Halaman Dossier Pengajuan Izin Mandiri (Manager Review)', [
    'Daftar Pengajuan',
    'Alasan Permohonan',
    'Keputusan Peninjauan Manajerial'
]) && $allPassed;

// 3. Tampilan Manager
echo "\n--- 3. MODUL MANAJER (MANAGER) ---\n";
$allPassed = testRoute('/', $manager, 'Dashboard Supervisi Manager', [
    'Halo,',
    'Supervisi Cabang',
    'Layar Kiosk QR Toko',
    'Jadwal Shift Hari Ini'
]) && $allPassed;

$allPassed = testRoute('/shifts', $manager, 'Jadwal Shift Bersih', [
    'Jadwal Shift Kerja',
    '+ Buat Jadwal Shift Baru',
    'data-searchable-select'
]) && $allPassed;

$allPassed = testRoute('/shifts/create', $manager, 'Halaman Buat Shift Mandiri', [
    'Buat Jadwal Shift Baru',
    'Alokasi Karyawan & Cabang',
    'Tanggal & Jam Kerja',
    'Simpan Draf Shift'
]) && $allPassed;

$firstShift = \App\Models\Shift::first();
if ($firstShift) {
    $allPassed = testRoute('/shifts/' . $firstShift->id, $manager, 'Halaman Dossier Shift Mandiri', [
        'Daftar Shift',
        'Cabang & Lokasi Tugas',
        'Catatan Presensi Aktual'
    ]) && $allPassed;

    $allPassed = testRoute('/shifts/' . $firstShift->id . '/edit', $manager, 'Halaman Revisi Shift Mandiri', [
        'Revisi Jadwal Shift',
        'Penyesuaian Waktu Kerja',
        'Alasan & Justifikasi Revisi'
    ]) && $allPassed;
}

$allPassed = testRoute('/attendance-review', $manager, 'Tinjau Presensi', [
    'Tinjau & Koreksi Presensi',
    'Pengecualian Menunggu Keputusan',
    'data-searchable-select'
]) && $allPassed;

$allPassed = testRoute('/branch-qr', $manager, 'Kiosk QR Cabang', [
    'data-qr-url',
    'qr-code',
    'qr-timer'
]) && $allPassed;

// 4. Tampilan Super Admin
echo "\n--- 4. MODUL SUPER ADMIN (ADMIN) ---\n";
$allPassed = testRoute('/', $admin, 'Dashboard Eksekutif Super Admin', [
    'Halo,',
    'Pusat Tindakan Cepat',
    'Total Karyawan Aktif',
    'Siaran Langsung Presensi'
]) && $allPassed;

$allPassed = testRoute('/people', $admin, 'Direktori Karyawan Bersih', [
    'Direktori Karyawan',
    '+ Tambah Karyawan Baru',
    'data-searchable-select'
]) && $allPassed;

$allPassed = testRoute('/people/create', $admin, 'Halaman Tambah Karyawan Mandiri', [
    'Pendaftaran Karyawan Baru',
    'Identitas Pribadi & Akun',
    'Simpan Karyawan Baru',
    'data-searchable-select'
]) && $allPassed;

$allPassed = testRoute('/people/' . $employee->id, $admin, 'Halaman Dossier Karyawan Mandiri', [
    'Dossier Karyawan',
    'Riwayat Presensi Terbaru',
    'Gaji Pokok Bulanan'
]) && $allPassed;

$allPassed = testRoute('/people/' . $employee->id . '/edit', $admin, 'Halaman Ubah Karyawan Mandiri', [
    'Ubah Data Karyawan',
    'Simpan Perubahan Data',
    'reset_device'
]) && $allPassed;

$allPassed = testRoute('/branches', $admin, 'Master Cabang Bersih', [
    'Master Cabang Perusahaan',
    'Tambah Cabang Baru',
    'Titik Koordinat GPS'
]) && $allPassed;

$allPassed = testRoute('/branches/create', $admin, 'Halaman Tambah Cabang Mandiri', [
    'Pendaftaran Lokasi Operasional',
    'Peta Interaktif Penentuan Lokasi Cabang',
    'Simpan Cabang Baru'
]) && $allPassed;

$allPassed = testRoute('/branches/1', $admin, 'Halaman Dossier Cabang Mandiri', [
    'Daftar Staf Cabang Ini',
    'Radius Geofence',
    'QR Presensi Cabang'
]) && $allPassed;

$allPassed = testRoute('/branches/1/edit', $admin, 'Halaman Ubah Cabang Mandiri', [
    'Ubah Data Cabang',
    'Kode Cabang (Permanen)',
    'Simpan Perubahan Cabang'
]) && $allPassed;

$allPassed = testRoute('/positions', $admin, 'Master Jabatan Bersih', [
    'Master Jabatan & Posisi',
    'Tambah Jabatan Baru',
    'Nama Jabatan'
]) && $allPassed;

$allPassed = testRoute('/positions/create', $admin, 'Halaman Tambah Jabatan Mandiri', [
    'Struktur Organisasi Perusahaan',
    'Tips Penamaan Jabatan',
    'Simpan Jabatan Baru'
]) && $allPassed;

$allPassed = testRoute('/positions/1', $admin, 'Halaman Detail Jabatan Mandiri', [
    'Daftar Karyawan dengan Jabatan Ini',
    'Total Karyawan Aktif',
    'Ubah Nama'
]) && $allPassed;

$allPassed = testRoute('/positions/1/edit', $admin, 'Halaman Ubah Jabatan Mandiri', [
    'Ubah Nama Jabatan',
    'Perubahan Struktur Posisi',
    'Simpan Perubahan'
]) && $allPassed;

$allPassed = testRoute('/import', $admin, 'Wizard Impor Karyawan', [
    'Impor Data Karyawan',
    'Pilih Berkas Spreadsheet',
    'accept=".csv,.xlsx"'
]) && $allPassed;

$allPassed = testRoute('/payroll', $admin, 'Dashboard Payroll', [
    'Kalkulasi & Rekap Payroll',
    'Otomasi Penggajian & Audit',
    'Glosarium & Panduan Perhitungan Gaji',
    'Rekapitulasi gaji'
]) && $allPassed;

$allPassed = testRoute('/payroll/slip/' . $employee->id, $admin, 'Slip Gaji Karyawan (Admin Access)', [
    'Slip Gaji Karyawan',
    'HR GROUP ENTERPRISE',
    '1. Pendapatan (Earnings)',
    '2. Potongan (Deductions)',
    'Take Home Pay'
]) && $allPassed;

$allPassed = testRoute('/payroll/slip/' . $employee->id, $employee, 'Slip Gaji Karyawan (Self Access)', [
    'Slip Gaji Karyawan',
    'HR GROUP ENTERPRISE',
    '1. Pendapatan (Earnings)',
    '2. Potongan (Deductions)',
    'Take Home Pay'
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
    echo "🎉 ALL BLADE VIEWS & E2E FLOWS RENDERED SUCCESSFULLY (incl. Payroll Slip)!\n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED. CHECK LOGS ABOVE.\n";
    echo "========================================================\n";
    exit(1);
}


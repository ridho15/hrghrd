<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeUsabilityPriorityOneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Karyawan melihat menu navigasi "Slip Gaji Saya" di sidebar dan dashboard.
     */
    public function test_employee_sees_slip_gaji_menu_in_sidebar_and_dashboard(): void
    {
        $branch = $this->createBranch(['name' => 'Cabang Sudirman']);
        $employee = $this->actingAsEmployee($branch);

        $response = $this->get('/');
        $response->assertOk();

        // Verifikasi menu sidebar
        $response->assertSee('Slip Gaji Saya');
        $response->assertSee(route('payroll.slip', $employee->id));

        // Verifikasi widget dashboard
        $response->assertSee('Slip Gaji Karyawan');
        $response->assertSee('Lihat Slip Gaji');
    }

    /**
     * 2. Karyawan dapat membuka slip gajinya sendiri, tetapi dilarang (403) membuka slip gaji orang lain.
     */
    public function test_employee_can_view_own_payroll_slip_and_cannot_view_others(): void
    {
        $branch = $this->createBranch();
        $employeeA = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'name' => 'Karyawan A']);
        $employeeB = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'name' => 'Karyawan B']);

        // Login sebagai Karyawan A
        $this->actingAs($employeeA);

        // Buka slip sendiri -> OK (200)
        $currentMonth = now('Asia/Jakarta')->format('Y-m');
        $responseOwn = $this->get(route('payroll.slip', ['userId' => $employeeA->id, 'month' => $currentMonth]));
        $responseOwn->assertOk();
        $responseOwn->assertSee($employeeA->name);

        // Buka slip Karyawan B -> 403 Forbidden
        $responseOther = $this->get(route('payroll.slip', ['userId' => $employeeB->id, 'month' => $currentMonth]));
        $responseOther->assertForbidden();
    }

    /**
     * 3. Halaman Presensi Hari Ini menampilkan penanggalan shift dalam bahasa Indonesia.
     */
    public function test_attendance_today_uses_indonesian_date_format_for_scheduled_shifts(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $today = Carbon::now('Asia/Jakarta');
        $recentShiftDate = $today->copy()->subDays(2);
        $expectedIndonesianDay = $recentShiftDate->translatedFormat('l, d M Y');

        // Buat shift 2 hari lalu agar masuk ke daftar riwayat presensi terbaru
        $this->createShift($employee, $branch, [
            'start_at' => $recentShiftDate->copy()->setTime(8, 0, 0)->toDateTimeString(),
            'end_at'   => $recentShiftDate->copy()->setTime(17, 0, 0)->toDateTimeString(),
            'status'   => 'approved',
        ]);

        $response = $this->get(route('attendance.today'));
        $response->assertOk();
        $response->assertSee($expectedIndonesianDay);
    }

    /**
     * 4. Halaman Riwayat Cuti (/leave) merender Card View responsif khusus layar smartphone.
     */
    public function test_leave_page_renders_mobile_card_view_for_leave_requests(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        LeaveRequest::create([
            'user_id' => $employee->id,
            'created_by' => $employee->id,
            'type' => 'leave',
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-21',
            'reason' => 'Keperluan keluarga di luar kota',
            'status' => 'pending',
        ]);

        $response = $this->get(route('leave'));
        $response->assertOk();

        // Verifikasi elemen card view mobile
        $response->assertSee('md:hidden divide-y divide-slate-100', false);
        $response->assertSee('Lihat Detail');
        $response->assertSee('Keperluan keluarga di luar kota');
        $response->assertSee('Cuti Tahunan');
        $response->assertSee('Durasi: 2 Hari');
    }

    /**
     * 5. Halaman Profil Karyawan merapikan rentang tanggal cuti ke format bahasa Indonesia.
     */
    public function test_people_profile_renders_indonesian_leave_dates(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        LeaveRequest::create([
            'user_id' => $employee->id,
            'created_by' => $employee->id,
            'type' => 'sick',
            'start_date' => '2026-10-25 00:00:00',
            'end_date' => '2026-10-25 00:00:00',
            'reason' => 'Demam tinggi dan flu',
            'status' => 'approved',
        ]);

        $response = $this->get(route('people.show', $employee->id));
        $response->assertOk();

        // Tanggal terformat ringkas
        $response->assertSee('25 Okt 2026');
        // Tidak lagi menampilkan raw time 00:00:00 pada rentang tanggal
        $response->assertDontSee('2026-10-25 00:00:00 s.d. 2026-10-25 00:00:00');
    }
}

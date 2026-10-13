<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCalendarAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Karyawan dapat mengakses halaman kalender tanpa error (HTTP 200).
     */
    public function test_employee_can_view_calendar_page_successfully(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at'   => '2026-10-15 16:00:00',
            'status'   => 'approved',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertSee('Kalender Jadwal Shift');
        $response->assertSee($employee->name);
    }

    /**
     * 2. Kalender karyawan secara default otomatis terisolasi ke jadwal miliknya sendiri (scope=me).
     */
    public function test_employee_calendar_defaults_to_own_shifts(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee', 'name' => 'Budi Karyawan A']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee', 'name' => 'Siti Karyawan B']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-14 08:00:00',
            'end_at'   => '2026-10-14 16:00:00',
            'status'   => 'approved',
        ]);

        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => '2026-10-14 08:00:00',
            'end_at'   => '2026-10-14 16:00:00',
            'status'   => 'approved',
        ]);

        // Login sebagai Karyawan A
        $this->actingAs($empA);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertSee('modal-shift-' . $shiftA->id);
        $response->assertDontSee('modal-shift-' . $shiftB->id);

        // Verifikasi collection data shifts hanya memuat shift Budi
        $viewShifts = $response->viewData('shifts');
        $this->assertCount(1, $viewShifts);
        $this->assertTrue($viewShifts->contains('id', $shiftA->id));
        $this->assertFalse($viewShifts->contains('id', $shiftB->id));
    }

    /**
     * 3. Karyawan dapat melihat roster seluruh cabang secara read-only ketika scope=branch.
     */
    public function test_employee_can_view_branch_roster_when_requested(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee', 'name' => 'Budi Karyawan A']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee', 'name' => 'Siti Karyawan B']);

        $this->createShift($empA, $branch, [
            'start_at' => '2026-10-14 08:00:00',
            'end_at'   => '2026-10-14 16:00:00',
            'status'   => 'approved',
        ]);

        $this->createShift($empB, $branch, [
            'start_at' => '2026-10-14 08:00:00',
            'end_at'   => '2026-10-14 16:00:00',
            'status'   => 'approved',
        ]);

        // Login sebagai Karyawan A dan buka scope=branch
        $this->actingAs($empA);

        $response = $this->get('/shifts/calendar?month=2026-10&scope=branch');
        $response->assertOk();
        $response->assertSee('Budi Karyawan A');
        $response->assertSee('Siti Karyawan B');
        $response->assertSee('Roster Cabang');
    }

    /**
     * 4. Karyawan tidak melihat tombol manajerial (+ Buat Shift, Tabel Shift, dsb) yang dapat memicu 403.
     */
    public function test_employee_cannot_see_manager_action_buttons(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $this->createShift($employee, $branch, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at'   => '2026-10-15 16:00:00',
            'status'   => 'approved',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertDontSee('+ Buat Shift');
        $response->assertDontSee('Tabel Jadwal Shift');
        $response->assertDontSee('Tugaskan Pengganti');
        $response->assertDontSee('Lihat Roster Lengkap');
    }

    /**
     * 5. Tombol permohonan tukar shift hanya tampil pada shift milik karyawan itu sendiri.
     */
    public function test_employee_can_only_request_exchange_for_their_own_shift(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee', 'name' => 'Budi Karyawan A']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee', 'name' => 'Siti Karyawan B']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-16 08:00:00',
            'end_at'   => '2026-10-16 16:00:00',
            'status'   => 'approved',
        ]);

        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => '2026-10-16 08:00:00',
            'end_at'   => '2026-10-16 16:00:00',
            'status'   => 'approved',
        ]);

        // Login sebagai Karyawan A dalam mode roster cabang
        $this->actingAs($empA);
        $response = $this->get('/shifts/calendar?month=2026-10&scope=branch');
        $response->assertOk();

        // Modal exchange untuk shift A milik Budi harus ada
        $response->assertSee('modal-exchange-shift-' . $shiftA->id);
        // Modal exchange untuk shift B milik Siti TIDAK boleh dibuat untuk Budi
        $response->assertDontSee('modal-exchange-shift-' . $shiftB->id);
    }

    /**
     * 6. Karyawan memiliki menu navigasi sidebar dan tombol pintas di Dashboard & Presensi Hari Ini.
     */
    public function test_employee_sidebar_and_dashboard_contain_calendar_links(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        // Sidebar pada halaman home
        $resHome = $this->get('/');
        $resHome->assertOk();
        $resHome->assertSeeText('Jadwal & Presensi Saya');
        $resHome->assertSeeText('Kalender Kerja Saya');

        // Halaman presensi hari ini
        $resToday = $this->get('/attendance/today');
        $resToday->assertOk();
        $resToday->assertSeeText('Kalender Kerja Saya');
        $resToday->assertSeeText('Buka Kalender Kerja & Riwayat Presensi');
    }

    /**
     * 7. Karyawan tetap terlindungi dari rute manajerial /shifts dan /shifts/{id} (403 Forbidden).
     */
    public function test_employee_cannot_access_manager_routes(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $shift = $this->createShift($employee, $branch);

        $resShifts = $this->get('/shifts');
        $resShifts->assertForbidden();

        $resShow = $this->get('/shifts/' . $shift->id);
        $resShow->assertForbidden();
    }
}

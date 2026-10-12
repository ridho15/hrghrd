<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceException;
use App\Models\AuditLog;
use App\Models\ShiftExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminOverrideAndManualAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Memastikan Super Admin dapat menghapus paksa jadwal shift meskipun sudah berstatus
     * disetujui (approved) dan memiliki relasi rekaman absensi, tiket kendala, serta pertukaran shift.
     */
    public function test_super_admin_can_force_delete_approved_shift_with_attendance_and_exceptions(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-12 08:00:00',
            'end_at' => '2026-10-12 17:00:00',
            'status' => 'approved',
        ]);

        // Buat data absensi dan tiket kendala terkait shift ini
        $attendance = Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'status' => 'present',
            'checkin_at' => '2026-10-12 08:00:00',
            'checkout_at' => '2026-10-12 17:00:00',
            'late_minutes' => 0,
            'late_units' => 0,
            'overtime_minutes' => 0,
        ]);

        $exception = AttendanceException::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'action' => 'in',
            'reason' => 'Tiket kendala presensi untuk pengujian penghapusan paksa.',
            'status' => 'approved',
        ]);

        $this->actingAsAdmin();

        $response = $this->post(route('shifts.destroy', $shift->id));

        $response->assertRedirect(route('shifts'));
        $response->assertSessionHas('ok');

        // Pastikan shift dan seluruh data anak terhapus bersih tanpa violation error
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
        $this->assertDatabaseMissing('attendance_exceptions', ['id' => $exception->id]);

        // Pastikan audit trail mencatat aksi force_delete oleh Super Admin
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'shift',
            'subject_id' => $shift->id,
            'action' => 'force_delete',
        ]);
    }

    /**
     * Memastikan Manager cabang biasa tetap dilarang menghapus shift yang sudah memiliki absensi.
     */
    public function test_branch_manager_forbidden_from_deleting_shift_with_attendance(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-12 08:00:00',
            'end_at' => '2026-10-12 17:00:00',
            'status' => 'approved',
        ]);

        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'status' => 'present',
            'checkin_at' => '2026-10-12 08:00:00',
            'late_minutes' => 0,
            'late_units' => 0,
            'overtime_minutes' => 0,
        ]);

        $response = $this->post(route('shifts.destroy', $shift->id));

        $response->assertSessionHasErrors(['shift']);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    /**
     * Memastikan Super Admin dapat membuat entri presensi manual dari nol untuk shift kosong.
     */
    public function test_super_admin_can_create_manual_attendance_for_empty_shift(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-12 08:00:00',
            'end_at' => '2026-10-12 17:00:00',
            'status' => 'approved',
        ]);

        $this->actingAsAdmin();

        $response = $this->post(route('shifts.attendance.manual', $shift->id), [
            'status' => 'present',
            'checkin_at' => '2026-10-12 08:00:00',
            'checkout_at' => '2026-10-12 17:00:00',
            'reason' => 'Entri kehadiran manual Super Admin karena karyawan lupa absensi.',
            'force_override_period' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('ok', 'Presensi manual berhasil dicatat oleh Super Admin.');

        $this->assertDatabaseHas('attendances', [
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'status' => 'present',
            'late_minutes' => 0,
            'late_units' => 0,
        ]);

        $attendance = Attendance::where('shift_id', $shift->id)->first();
        $this->assertNotNull($attendance);
        $this->assertTrue(collect($attendance->flags)->contains('manual_admin_entry', true));

        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'attendance',
            'subject_id' => $attendance->id,
            'action' => 'manual_admin_create',
        ]);
    }

    /**
     * Memastikan entri presensi manual mengkalkulasi keterlambatan dan lembur dengan akurat.
     */
    public function test_manual_attendance_calculates_late_and_overtime_accurately(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-12 08:00:00',
            'end_at' => '2026-10-12 17:00:00',
            'status' => 'approved',
        ]);

        $this->actingAsAdmin();

        // Datang telat 30 menit (08:30) dan pulang lembur 19:00 (120 menit ekstra > threshold 90 menit)
        $response = $this->post(route('shifts.attendance.manual', $shift->id), [
            'status' => 'late',
            'checkin_at' => '2026-10-12 08:30:00',
            'checkout_at' => '2026-10-12 19:00:00',
            'reason' => 'Verifikasi kehadiran terlambat dengan tugas lembur disetujui.',
            'force_override_period' => 1,
        ]);

        $response->assertRedirect();

        $attendance = Attendance::where('shift_id', $shift->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals(30, $attendance->late_minutes);
        $this->assertGreaterThan(0, $attendance->late_units);
        $this->assertGreaterThan(0, $attendance->overtime_minutes);
    }

    /**
     * Memastikan Super Admin dapat menimpa (override) data absensi yang sudah ada sebelumnya.
     */
    public function test_super_admin_can_override_existing_attendance_manually(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-12 08:00:00',
            'end_at' => '2026-10-12 17:00:00',
            'status' => 'approved',
        ]);

        $attendance = Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'status' => 'present',
            'checkin_at' => '2026-10-12 08:00:00',
            'checkout_at' => '2026-10-12 17:00:00',
            'late_minutes' => 0,
            'late_units' => 0,
            'overtime_minutes' => 0,
        ]);

        $this->actingAsAdmin();

        // Timpa menjadi Alfa (absent)
        $response = $this->post(route('shifts.attendance.manual', $shift->id), [
            'status' => 'absent',
            'checkin_at' => null,
            'checkout_at' => null,
            'reason' => 'Koreksi data investigasi: karyawan terbukti tidak berada di lokasi.',
            'force_override_period' => 1,
        ]);

        $response->assertRedirect();

        $attendance->refresh();
        $this->assertEquals('absent', $attendance->status);
        $this->assertNull($attendance->checkin_at);
        $this->assertNull($attendance->checkout_at);

        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'attendance',
            'subject_id' => $attendance->id,
            'action' => 'manual_admin_update',
        ]);
    }

    /**
     * Memastikan Manager cabang dilarang mengakses route manual attendance Super Admin.
     */
    public function test_branch_manager_cannot_call_manual_attendance(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch);

        $response = $this->post(route('shifts.attendance.manual', $shift->id), [
            'status' => 'present',
            'checkin_at' => '2026-10-12 08:00:00',
            'checkout_at' => '2026-10-12 17:00:00',
            'reason' => 'Percobaan entri manual oleh manager.',
        ]);

        $response->assertForbidden();
    }
}

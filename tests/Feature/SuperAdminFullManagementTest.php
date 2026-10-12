<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceException;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminFullManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_force_delete_shift_with_existing_attendance_and_exceptions(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-12 08:00:00',
            'end_at'   => '2026-10-12 17:00:00',
            'status'   => 'approved',
        ]);

        $attendance = Attendance::create([
            'shift_id'         => $shift->id,
            'user_id'          => $employee->id,
            'status'           => 'present',
            'checkin_at'       => '2026-10-12 08:00:00',
            'checkout_at'      => '2026-10-12 17:00:00',
            'late_minutes'     => 0,
            'late_units'       => 0,
            'overtime_minutes' => 0,
        ]);

        $exception = AttendanceException::create([
            'shift_id' => $shift->id,
            'user_id'  => $employee->id,
            'action'   => 'in',
            'reason'   => 'Kendala GPS saat check-in',
            'status'   => 'approved',
        ]);

        $this->actingAsAdmin();

        $response = $this->post(route('shifts.destroy', $shift->id));

        $response->assertRedirect(route('shifts'));
        $response->assertSessionHas('ok');

        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
        $this->assertDatabaseMissing('attendance_exceptions', ['id' => $exception->id]);

        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'shift',
            'subject_id'   => $shift->id,
            'action'       => 'admin_force_delete',
        ]);
    }

    public function test_branch_manager_forbidden_from_deleting_shift_with_attendance(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-12 08:00:00',
            'end_at'   => '2026-10-12 17:00:00',
            'status'   => 'approved',
        ]);

        Attendance::create([
            'shift_id'         => $shift->id,
            'user_id'          => $employee->id,
            'status'           => 'present',
            'checkin_at'       => '2026-10-12 08:00:00',
            'late_minutes'     => 0,
            'late_units'       => 0,
            'overtime_minutes' => 0,
        ]);

        $response = $this->post(route('shifts.destroy', $shift->id));

        $response->assertSessionHasErrors(['shift']);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    public function test_super_admin_can_destroy_attendance_record_directly(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch);

        $attendance = Attendance::create([
            'shift_id'         => $shift->id,
            'user_id'          => $employee->id,
            'status'           => 'present',
            'checkin_at'       => '2026-10-12 08:00:00',
            'late_minutes'     => 0,
            'late_units'       => 0,
            'overtime_minutes' => 0,
        ]);

        $this->actingAsAdmin();

        $response = $this->delete(route('attendance.destroy', $attendance->id));

        $response->assertRedirect();
        $response->assertSessionHas('ok');

        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'attendance',
            'subject_id'   => $attendance->id,
            'action'       => 'admin_delete',
        ]);
    }

    public function test_branch_manager_forbidden_from_destroying_attendance_record(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch);

        $attendance = Attendance::create([
            'shift_id'         => $shift->id,
            'user_id'          => $employee->id,
            'status'           => 'present',
            'checkin_at'       => '2026-10-12 08:00:00',
            'late_minutes'     => 0,
            'late_units'       => 0,
            'overtime_minutes' => 0,
        ]);

        $response = $this->delete(route('attendance.destroy', $attendance->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
    }

    public function test_super_admin_can_edit_and_update_approved_shift(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at'   => '2026-10-15 16:00:00',
            'status'   => 'approved',
        ]);

        $this->actingAsAdmin();

        $editResponse = $this->get(route('shifts.edit', $shift->id));
        $editResponse->assertOk();

        $updateResponse = $this->post(route('shifts.update', $shift->id), [
            'date'       => '2026-10-15',
            'start_time' => '09:00',
            'end_time'   => '17:00',
            'reason'     => 'Revisi jadwal resmi disetujui Super Admin',
        ]);

        $updateResponse->assertRedirect(route('shifts.show', $shift->id));
        $shift->refresh();
        $this->assertEquals('2026-10-15 09:00:00', $shift->start_at->toDateTimeString());
        $this->assertEquals('2026-10-15 17:00:00', $shift->end_at->toDateTimeString());
    }

    public function test_branch_manager_cannot_edit_approved_shift(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at'   => '2026-10-15 16:00:00',
            'status'   => 'approved',
        ]);

        $editResponse = $this->get(route('shifts.edit', $shift->id));
        $editResponse->assertStatus(409);

        $updateResponse = $this->post(route('shifts.update', $shift->id), [
            'date'       => '2026-10-15',
            'start_time' => '09:00',
            'end_time'   => '17:00',
            'reason'     => 'Manager mencoba mengubah shift approved',
        ]);
        $updateResponse->assertStatus(409);
    }

    public function test_payroll_view_displays_warning_badge_when_employee_bank_account_is_missing(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'bank_account_number' => null,
            'active' => true,
        ]);

        $this->createShift($employee, $branch, [
            'start_at' => '2026-10-12 08:00:00',
            'end_at'   => '2026-10-12 17:00:00',
            'status'   => 'approved',
        ]);

        $this->actingAsAdmin();

        $response = $this->get(route('payroll.index', ['branch_id' => $branch->id, 'month' => '2026-10']));
        $response->assertOk();
        $response->assertSee('Rekening Belum Diisi');
    }
}

<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftSchedulingNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_create_shift_in_locked_payroll_month(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        // Lock payroll for 2026-08
        PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => '2026-08',
            'status' => 'locked',
        ]);

        $response = $this->post('/shifts', [
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'date' => '2026-08-15',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(409);
    }

    public function test_manager_cannot_update_shift_that_is_already_approved(): void
    {
        $branch = $this->createBranch();
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-09-15 09:00:00',
            'end_at' => '2026-09-15 17:00:00',
            'status' => 'approved',
            'version' => 1,
        ]);

        $response = $this->post("/shifts/{$shift->id}/update", [
            'date' => '2026-09-15',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'reason' => 'Perubahan jam dinas oleh manager',
            'expected_version' => 1,
        ]);

        $response->assertStatus(409);
        $this->assertSame('2026-09-15 09:00:00', $shift->fresh()->start_at->toDateTimeString());
    }

    public function test_admin_can_update_approved_shift_when_no_attendance_exists(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-09-15 09:00:00',
            'end_at' => '2026-09-15 17:00:00',
            'status' => 'approved',
            'version' => 1,
        ]);

        $response = $this->post("/shifts/{$shift->id}/update", [
            'date' => '2026-09-15',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'reason' => 'Koreksi jam kerja oleh super admin',
            'expected_version' => 1,
        ]);

        $response->assertRedirect("/shifts/{$shift->id}");
        $this->assertSame('2026-09-15 10:00:00', $shift->fresh()->start_at->toDateTimeString());
        $this->assertSame('2026-09-15 18:00:00', $shift->fresh()->end_at->toDateTimeString());
        $this->assertSame('approved', $shift->fresh()->status);
        $this->assertSame(2, $shift->fresh()->version);
    }

    public function test_admin_cannot_update_shift_that_already_has_attendance(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-09-15 09:00:00',
            'end_at' => '2026-09-15 17:00:00',
            'status' => 'approved',
            'version' => 1,
        ]);

        \App\Models\Attendance::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'checkin_at' => '2026-09-15 08:55:00',
            'status' => 'present',
        ]);

        $response = $this->post("/shifts/{$shift->id}/update", [
            'date' => '2026-09-15',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'reason' => 'Perubahan jadwal yang sudah ada absensi',
            'expected_version' => 1,
        ]);

        $response->assertStatus(409);
        $this->assertSame('2026-09-15 09:00:00', $shift->fresh()->start_at->toDateTimeString());
    }

    public function test_optimistic_locking_prevents_lost_updates_on_stale_version(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        // Shift currently at version 2 (already updated once)
        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-09-15 09:00:00',
            'end_at' => '2026-09-15 17:00:00',
            'status' => 'draft',
            'version' => 2,
        ]);

        // Another manager sends an update with stale expected_version = 1
        $response = $this->post("/shifts/{$shift->id}/update", [
            'date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'reason' => 'Perubahan jam dinas',
            'expected_version' => 1, // Stale version!
        ]);

        $response->assertStatus(409);
        $this->assertSame(2, $shift->fresh()->version);
    }

    public function test_employee_is_forbidden_from_approving_shifts(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch, ['status' => 'draft']);

        $this->actingAs($employee);
        $response = $this->post("/shifts/{$shift->id}/approve");

        $response->assertForbidden();
        $this->assertSame('draft', $shift->fresh()->status);
    }
}

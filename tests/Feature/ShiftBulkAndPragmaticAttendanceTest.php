<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditTrail;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftBulkAndPragmaticAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_shift_fails_validation_when_end_date_before_start_date(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branch->id,
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-10', // end before start!
            'days_of_week' => [1, 2, 3, 4, 5],
            'user_ids' => [$employee->id],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_recurring_shift_fails_validation_when_range_exceeds_90_days(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branch->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-05-01', // > 90 days
            'days_of_week' => [1, 2, 3, 4, 5],
            'user_ids' => [$employee->id],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_recurring_shift_fails_validation_when_no_active_days_selected(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branch->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-07',
            'days_of_week' => [], // Empty days
            'user_ids' => [$employee->id],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertSessionHasErrors('days_of_week');
    }

    public function test_recurring_shift_fails_when_user_ids_empty_and_select_all_branch_false(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();

        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branch->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-07',
            'days_of_week' => [1, 2, 3],
            'select_all_branch' => 0,
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertSessionHasErrors('user_ids');
    }

    public function test_recurring_shift_fails_when_user_does_not_belong_to_target_branch(): void
    {
        $this->actingAsAdmin();
        $branchA = $this->createBranch(['name' => 'Cabang A']);
        $branchB = $this->createBranch(['name' => 'Cabang B']);

        // Employee belongs to Branch A
        $employeeA = $this->createUser(['branch_id' => $branchA->id]);

        // Attempting to schedule employee A on Branch B
        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branchB->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'days_of_week' => [1, 2, 3, 4, 5],
            'user_ids' => [$employeeA->id],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertSessionHasErrors('shift');
        $this->assertDatabaseMissing('shifts', [
            'user_id' => $employeeA->id,
            'branch_id' => $branchB->id,
        ]);
    }

    public function test_manager_cannot_create_recurring_shifts_for_other_branches(): void
    {
        $branchManager = $this->createBranch(['name' => 'Cabang Manager']);
        $branchOther = $this->createBranch(['name' => 'Cabang Lain']);

        $manager = $this->actingAsManager($branchManager);
        $employeeOther = $this->createUser(['branch_id' => $branchOther->id]);

        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branchOther->id, // forbidden for this manager
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'days_of_week' => [1, 2, 3],
            'user_ids' => [$employeeOther->id],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertForbidden();
    }

    public function test_recurring_shift_fails_when_payroll_month_is_locked(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        // Lock payroll for 2026-11
        PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => '2026-11',
            'status' => 'locked',
        ]);

        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branch->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
            'days_of_week' => [1, 2, 3, 4, 5],
            'user_ids' => [$employee->id],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseMissing('shifts', [
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_recurring_shift_skips_overlapping_shifts_without_failing_batch(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        // Create an existing shift on Monday 2026-10-05
        $this->createShift($employee, $branch, [
            'start_at' => '2026-10-05 08:00:00',
            'end_at' => '2026-10-05 17:00:00',
            'status' => 'approved',
        ]);

        // Request recurring shifts covering Mon (2026-10-05) and Tue (2026-10-06)
        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branch->id,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-06',
            'days_of_week' => [1, 2], // Mon & Tue
            'user_ids' => [$employee->id],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertRedirect('/shifts');
        $response->assertSessionHas('ok');

        // Tuesday shift must be created
        $this->assertDatabaseHas('shifts', [
            'user_id' => $employee->id,
            'start_at' => '2026-10-06 08:00:00',
            'end_at' => '2026-10-06 17:00:00',
        ]);

        // Total shifts for employee should be 2 (1 existing + 1 new), not 3 (no duplicate on 2026-10-05)
        $this->assertSame(2, Shift::where('user_id', $employee->id)->count());
    }

    public function test_recurring_shift_by_admin_with_auto_approve_creates_approved_shifts(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branch->id,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
            'days_of_week' => [1, 2, 3], // Mon, Tue, Wed (3 shifts)
            'user_ids' => [$employee->id],
            'start_time' => '08:00',
            'end_time' => '17:00',
            'auto_approve' => 1,
        ]);

        $response->assertRedirect('/shifts');

        $shifts = Shift::where('user_id', $employee->id)->get();
        $this->assertCount(3, $shifts);
        foreach ($shifts as $s) {
            $this->assertSame('approved', $s->status);
            $this->assertSame($admin->id, $s->approved_by);
            $this->assertNotNull($s->approved_at);
        }
    }

    public function test_recurring_shift_by_manager_ignores_auto_approve_and_creates_draft(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branch->id,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-06',
            'days_of_week' => [1, 2],
            'user_ids' => [$employee->id],
            'start_time' => '08:00',
            'end_time' => '17:00',
            'auto_approve' => 1, // Manager is not admin, should NOT auto-approve
        ]);

        $response->assertRedirect('/shifts');

        $shifts = Shift::where('user_id', $employee->id)->get();
        $this->assertCount(2, $shifts);
        foreach ($shifts as $s) {
            $this->assertSame('draft', $s->status);
            $this->assertNull($s->approved_by);
        }
    }

    public function test_select_all_branch_schedules_all_active_employees_in_branch(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();

        // 2 active employees in target branch
        $activeEmp1 = $this->createUser(['branch_id' => $branch->id, 'active' => true]);
        $activeEmp2 = $this->createUser(['branch_id' => $branch->id, 'active' => true]);

        // 1 inactive employee in target branch
        $inactiveEmp = $this->createUser(['branch_id' => $branch->id, 'active' => false]);

        // 1 employee in other branch
        $otherBranch = $this->createBranch();
        $otherEmp = $this->createUser(['branch_id' => $otherBranch->id, 'active' => true]);

        $response = $this->post('/shifts', [
            'mode' => 'recurring',
            'branch_id' => $branch->id,
            'select_all_branch' => 1,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
            'days_of_week' => [1],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertRedirect('/shifts');

        $this->assertDatabaseHas('shifts', ['user_id' => $activeEmp1->id, 'branch_id' => $branch->id]);
        $this->assertDatabaseHas('shifts', ['user_id' => $activeEmp2->id, 'branch_id' => $branch->id]);
        $this->assertDatabaseMissing('shifts', ['user_id' => $inactiveEmp->id]);
        $this->assertDatabaseMissing('shifts', ['user_id' => $otherEmp->id]);
    }

    public function test_pragmatic_attendance_works_with_automated_hidden_session_challenge(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_token_pragmatic')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);

        // Employee loads page, session sets attendance_challenge
        $sessionChallenge = 'auto_generated_challenge_' . uniqid();
        session(['attendance_challenge' => $sessionChallenge]);

        // Web form submits hidden challenge value matching session (no manual typing required)
        $response = $this->withCookie('hrd_device', 'dev_token_pragmatic')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $sessionChallenge,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'status' => 'present',
        ]);
    }

    public function test_attendance_with_mismatched_challenge_is_still_rejected_by_anti_fraud(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_token_pragmatic')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        session(['attendance_challenge' => 'valid_session_token_123']);

        // Fraudulent request sends a forged challenge
        $response = $this->withCookie('hrd_device', 'dev_token_pragmatic')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => 'forged_challenge_xyz',
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', [
            'shift_id' => $shift->id,
        ]);
    }
}

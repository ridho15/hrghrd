<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceException;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\PayrollLine;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollEngineNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_generate_payroll_on_locked_period(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();

        PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => '2026-08',
            'status' => 'locked',
        ]);

        $response = $this->post('/payroll/generate', [
            'branch_id' => $branch->id,
            'month' => '2026-08',
        ]);

        $response->assertStatus(409);
    }

    public function test_cannot_approve_payroll_before_month_ends(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $this->createUser(['branch_id' => $branch->id, 'hired_at' => '2026-01-01']);

        // Next month (clearly in the future)
        $futureMonth = now('Asia/Jakarta')->addMonths(2)->format('Y-m');

        // Create draft
        PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => $futureMonth,
            'status' => 'draft',
        ]);

        $response = $this->post('/payroll/approve', [
            'branch_id' => $branch->id,
            'month' => $futureMonth,
        ]);

        $response->assertStatus(409);
    }

    public function test_payroll_approval_blocked_by_gate_1_inactive_employee_without_ended_at(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();

        // Inactive employee without ended_at
        $this->createUser([
            'branch_id' => $branch->id,
            'hired_at' => '2026-01-01',
            'ended_at' => null, // MISSING!
            'active' => false,
        ]);

        $pastMonth = '2026-01';
        $run = PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => $pastMonth,
            'status' => 'draft',
        ]);

        $response = $this->post('/payroll/approve', [
            'branch_id' => $branch->id,
            'month' => $pastMonth,
        ]);

        $response->assertStatus(409);
        $this->assertSame('draft', $run->fresh()->status);
    }

    public function test_payroll_approval_blocked_by_gate_2_draft_shifts(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id, 'hired_at' => '2026-01-01']);

        $pastMonth = '2026-01';

        // Shift in draft status
        $this->createShift($employee, $branch, [
            'start_at' => "{$pastMonth}-10 09:00:00",
            'end_at' => "{$pastMonth}-10 17:00:00",
            'status' => 'draft', // DRAFT
        ]);

        PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => $pastMonth,
            'status' => 'draft',
        ]);

        $response = $this->post('/payroll/approve', [
            'branch_id' => $branch->id,
            'month' => $pastMonth,
        ]);

        $response->assertStatus(409);
    }

    public function test_payroll_approval_blocked_by_gate_4_attendance_without_checkout(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id, 'hired_at' => '2026-01-01']);

        $pastMonth = '2026-01';

        $shift = $this->createShift($employee, $branch, [
            'start_at' => "{$pastMonth}-10 09:00:00",
            'end_at' => "{$pastMonth}-10 17:00:00",
            'status' => 'approved',
        ]);

        // Checked in, but never checked out
        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'checkin_at' => "{$pastMonth}-10 09:00:00",
            'checkout_at' => null, // UNFINISHED ATTENDANCE
            'status' => 'present',
            'late_minutes' => 0,
            'late_units' => 0,
        ]);

        PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => $pastMonth,
            'status' => 'draft',
        ]);

        $response = $this->post('/payroll/approve', [
            'branch_id' => $branch->id,
            'month' => $pastMonth,
        ]);

        $response->assertStatus(409);
    }

    public function test_payroll_approval_blocked_by_gate_6_pending_leave_request(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id, 'hired_at' => '2026-01-01']);

        $pastMonth = '2026-01';

        // Pending leave in January
        LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'leave',
            'start_date' => "{$pastMonth}-15",
            'end_date' => "{$pastMonth}-16",
            'reason' => 'Pending vacation',
            'status' => 'pending', // PENDING
            'created_by' => $employee->id,
        ]);

        PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => $pastMonth,
            'status' => 'draft',
        ]);

        $response = $this->post('/payroll/approve', [
            'branch_id' => $branch->id,
            'month' => $pastMonth,
        ]);

        $response->assertStatus(409);
    }

    public function test_cannot_lock_unapproved_payroll_draft(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();

        $pastMonth = '2026-01';
        $run = PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => $pastMonth,
            'status' => 'draft', // STILL DRAFT
        ]);

        $response = $this->post('/payroll/lock', [
            'branch_id' => $branch->id,
            'month' => $pastMonth,
        ]);

        $response->assertStatus(409);
        $this->assertSame('draft', $run->fresh()->status);
    }

    public function test_cannot_export_csv_on_unapproved_draft(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();

        $pastMonth = '2026-01';
        PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => $pastMonth,
            'status' => 'draft', // DRAFT
        ]);

        $response = $this->get("/payroll/export?branch_id={$branch->id}&month={$pastMonth}");

        $response->assertStatus(409);
    }
}

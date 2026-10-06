<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeletionAndLifecycleActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_employee_active_status(): void
    {
        $admin = $this->actingAsAdmin();
        $user = $this->createUser(['active' => true]);

        $response = $this->post("/people/{$user->id}/toggle-status");
        $response->assertSessionHas('ok');
        $this->assertFalse($user->fresh()->active);
        $this->assertNotNull($user->fresh()->ended_at);

        $response = $this->post("/people/{$user->id}/toggle-status");
        $response->assertSessionHas('ok');
        $this->assertTrue($user->fresh()->active);
        $this->assertNull($user->fresh()->ended_at);
    }

    public function test_admin_cannot_toggle_or_delete_themselves(): void
    {
        $admin = $this->actingAsAdmin();

        $this->post("/people/{$admin->id}/toggle-status")
            ->assertSessionHasErrors('person');

        $this->post("/people/{$admin->id}/delete")
            ->assertSessionHasErrors('person');
    }

    public function test_admin_deletes_employee_without_history_permanently(): void
    {
        $this->actingAsAdmin();
        $user = $this->createUser();

        $response = $this->post("/people/{$user->id}/delete");
        $response->assertSessionHas('ok');
        $this->assertNull(User::find($user->id));
    }

    public function test_admin_deactivates_employee_safely_when_history_exists(): void
    {
        $this->actingAsAdmin();
        $user = $this->createUser(['active' => true]);
        $branch = $this->createBranch();
        $shift = $this->createShift($user, $branch);

        // Buat record absensi historis
        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'checkin_at' => Carbon::now(),
            'method' => 'qr',
        ]);

        $response = $this->post("/people/{$user->id}/delete");
        $response->assertSessionHas('ok');

        // Pastikan user TIDAK di-hard-delete, melainkan dinonaktifkan
        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertFalse($fresh->active);
        $this->assertNotNull($fresh->ended_at);
    }

    public function test_shift_without_attendance_can_be_cancelled(): void
    {
        $this->actingAsAdmin();
        $user = $this->createUser();
        $branch = $this->createBranch();
        $shift = $this->createShift($user, $branch);

        $response = $this->post("/shifts/{$shift->id}/delete");
        $response->assertSessionHas('ok');
        $this->assertNull(Shift::find($shift->id));
    }

    public function test_shift_with_attendance_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $user = $this->createUser();
        $branch = $this->createBranch();
        $shift = $this->createShift($user, $branch);

        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'checkin_at' => Carbon::now(),
            'method' => 'qr',
        ]);

        $response = $this->post("/shifts/{$shift->id}/delete");
        $response->assertSessionHasErrors('shift');
        $this->assertNotNull(Shift::find($shift->id));
    }

    public function test_pending_leave_request_can_be_cancelled(): void
    {
        $user = $this->actingAsEmployee();
        $branch = $this->createBranch();
        $user->update(['branch_id' => $branch->id]);

        $leave = LeaveRequest::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'leave',
            'start_date' => Carbon::now()->addDays(5)->toDateString(),
            'end_date' => Carbon::now()->addDays(6)->toDateString(),
            'reason' => 'Keperluan keluarga penting',
            'status' => 'pending',
        ]);

        $response = $this->post("/leave/{$leave->id}/delete");
        $response->assertSessionHas('ok');
        $this->assertNull(LeaveRequest::find($leave->id));
    }

    public function test_reviewed_leave_request_cannot_be_cancelled(): void
    {
        $user = $this->actingAsEmployee();
        $branch = $this->createBranch();
        $user->update(['branch_id' => $branch->id]);

        $leave = LeaveRequest::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'leave',
            'start_date' => Carbon::now()->addDays(5)->toDateString(),
            'end_date' => Carbon::now()->addDays(6)->toDateString(),
            'reason' => 'Keperluan keluarga penting',
            'status' => 'approved',
        ]);

        $response = $this->post("/leave/{$leave->id}/delete");
        $response->assertSessionHasErrors('leave');
        $this->assertNotNull(LeaveRequest::find($leave->id));
    }

    public function test_admin_can_reset_draft_payroll(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $month = Carbon::now()->format('Y-m');

        $run = PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => $month,
            'status' => 'draft',
            'base_rate' => 100000,
            'hourly_rate' => 12500,
        ]);

        $response = $this->post('/payroll/reset', [
            'branch_id' => $branch->id,
            'month' => $month,
        ]);

        $response->assertSessionHas('ok');
        $this->assertNull(PayrollRun::find($run->id));
    }

    public function test_resetting_locked_payroll_is_rejected(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $month = Carbon::now()->format('Y-m');

        $run = PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => $month,
            'status' => 'locked',
            'base_rate' => 100000,
            'hourly_rate' => 12500,
        ]);

        $response = $this->post('/payroll/reset', [
            'branch_id' => $branch->id,
            'month' => $month,
        ]);

        $response->assertSessionHasErrors('payroll');
        $this->assertNotNull(PayrollRun::find($run->id));
    }
}

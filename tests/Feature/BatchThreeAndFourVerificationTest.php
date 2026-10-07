<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceException;
use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LeaveService;
use App\Services\PayrollCalculator;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BatchThreeAndFourVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_exception_stores_claimed_time_and_calculates_correct_late_on_review(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $shift = Shift::create([
            'branch_id' => $branch->id,
            'user_id' => $employee->id,
            'start_at' => '2026-03-10 08:00:00',
            'end_at' => '2026-03-10 17:00:00',
            'status' => 'approved',
        ]);

        $this->actingAs($employee);

        // Submit exception with claimed arrival at 08:45 (late 45 minutes)
        $response = $this->post(route('attendance.exception', $shift->id), [
            'action' => 'in',
            'claimed_time' => '08:45',
            'reason' => 'Kamera smartphone bermasalah dan aplikasi baru bisa dibuka.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_exceptions', [
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'action' => 'in',
            'claimed_time' => '08:45',
        ]);

        $exception = AttendanceException::where('shift_id', $shift->id)->first();
        $this->assertNotNull($exception);

        // Manager reviews and approves WITHOUT filling actual_time (so it falls back to claimed_time)
        $this->actingAs($admin);
        $attendanceService = new AttendanceService();
        $attendanceService->reviewException($exception, $admin, 'approved', 'Bukti CCTV diverifikasi valid', null);

        $attendance = Attendance::where('shift_id', $shift->id)->first();
        $this->assertNotNull($attendance);
        $this->assertSame('2026-03-10 08:45:00', $attendance->checkin_at->toDateTimeString());
        $this->assertSame(45, $attendance->late_minutes);
        $this->assertGreaterThan(0, $attendance->late_units);
    }

    public function test_early_checkout_under_30_minutes_is_flagged_and_affects_payroll(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id, 'base_salary' => 6000000]);

        $shift = Shift::create([
            'branch_id' => $branch->id,
            'user_id' => $employee->id,
            'start_at' => '2026-03-10 08:00:00',
            'end_at' => '2026-03-10 17:00:00',
            'status' => 'approved',
        ]);

        // Create checkin at 08:00
        $attendance = Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'checkin_at' => '2026-03-10 08:00:00',
            'status' => 'present',
        ]);

        // Checkout after only 15 minutes (08:15)
        $this->actingAsAdmin();
        $attendanceService = new AttendanceService();
        $exception = AttendanceException::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'action' => 'out',
            'claimed_time' => '08:15',
            'reason' => 'Pulang karena mendadak sakit perut hebat.',
            'status' => 'pending',
        ]);

        $attendanceService->reviewException($exception, auth()->user(), 'approved', 'Disetujui pulang darurat', '08:15');

        $attendance->refresh();
        $this->assertSame('early_checkout', $attendance->status);
        $this->assertIsArray($attendance->flags);
        $this->assertTrue(collect($attendance->flags)->contains(fn ($f) => str_contains($f, 'Durasi kerja sangat singkat')));

        // Verify that PayrollCalculator treats early_checkout without full attendance as unpaid
        $calculator = new PayrollCalculator();
        $result = $calculator->calculate($employee, '2026-03');
        $this->assertContains('2026-03-10', $result['unpaid_dates']);
    }

    public function test_daily_overtime_is_strictly_capped_at_240_minutes(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id, 'base_salary' => 6000000]);

        $shift = Shift::create([
            'branch_id' => $branch->id,
            'user_id' => $employee->id,
            'start_at' => '2026-03-10 08:00:00',
            'end_at' => '2026-03-10 17:00:00',
            'status' => 'approved',
        ]);

        $attendance = Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'checkin_at' => '2026-03-10 08:00:00',
            'status' => 'present',
        ]);

        // Review checkout at 23:30 (shift ends at 17:00 => 390 extra minutes, 300 minutes raw overtime)
        $this->actingAsAdmin();
        $attendanceService = new AttendanceService();
        $exception = AttendanceException::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'action' => 'out',
            'claimed_time' => '23:30',
            'reason' => 'Lembur perbaikan server darurat semalaman.',
            'status' => 'pending',
        ]);

        $attendanceService->reviewException($exception, auth()->user(), 'approved', 'Lembur disetujui', '23:30');

        $attendance->refresh();
        // Maximum daily statutory overtime is capped at 240 minutes (4 hours)
        $this->assertSame(240, $attendance->overtime_minutes);
        $this->assertTrue(collect($attendance->flags)->contains(fn ($f) => str_contains($f, 'Lembur melebihi batas legal harian')));

        // Approve overtime for payroll
        $attendanceService->approveOvertime($attendance, auth()->user(), 'Disetujui manajemen');
        $attendance->refresh();

        $calculator = new PayrollCalculator();
        $result = $calculator->calculate($employee, '2026-03');
        // Overtime minutes calculated in payroll should not exceed 240
        $this->assertSame(240, $result['overtime_minutes']);
    }

    public function test_annual_leave_quota_prevents_overbooking_and_tracks_balance(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'annual_leave_quota' => 12,
        ]);

        $this->assertSame(12, $employee->remainingLeaveDays());
        $this->assertSame(0, $employee->usedLeaveDays((int) now('Asia/Jakarta')->year));

        $leaveService = new LeaveService();

        // 1. Trying to book 13 days of leave when only 12 are available fails with 422
        $nextMonth = now('Asia/Jakarta')->addMonth();
        $startStr = $nextMonth->copy()->startOfMonth()->toDateString();
        $endStr = $nextMonth->copy()->startOfMonth()->addDays(12)->toDateString(); // 13 days total

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $leaveService->submit($employee, [
            'type' => 'leave',
            'start_date' => $startStr,
            'end_date' => $endStr,
            'reason' => 'Cuti panjang liburan keliling luar kota.',
        ]);
    }

    public function test_employee_can_update_password_with_valid_current_password(): void
    {
        $employee = $this->createUser([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($employee);

        // 1. Wrong current password fails validation
        $failResponse = $this->from(route('people.show', $employee->id))
            ->post(route('password.update'), [
                'target_user_id' => $employee->id,
                'current_password' => 'WrongPassword!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]);

        $failResponse->assertSessionHasErrors(['current_password']);

        // 2. Correct current password succeeds
        $okResponse = $this->from(route('people.show', $employee->id))
            ->post(route('password.update'), [
                'target_user_id' => $employee->id,
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]);

        $okResponse->assertRedirect(route('people.show', $employee->id));
        $okResponse->assertSessionHas('ok');

        $employee->refresh();
        $this->assertTrue(Hash::check('NewPassword456!', $employee->password));
    }

    public function test_admin_can_reset_employee_password_directly(): void
    {
        $admin = $this->actingAsAdmin();
        $employee = $this->createUser([
            'password' => Hash::make('LupaSandi123!'),
        ]);

        $response = $this->post(route('password.update'), [
            'target_user_id' => $employee->id,
            'password' => 'AdminReset999!',
            'password_confirmation' => 'AdminReset999!',
        ]);

        $response->assertSessionHas('ok');

        $employee->refresh();
        $this->assertTrue(Hash::check('AdminReset999!', $employee->password));
    }
}

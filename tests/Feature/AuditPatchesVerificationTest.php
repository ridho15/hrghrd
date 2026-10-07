<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceException;
use App\Models\LeaveRequest;
use App\Models\LeaveDay;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LeaveService;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AuditPatchesVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_shift_for_their_own_branch(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $response = $this->post('/shifts', [
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'date' => '2026-11-10',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shifts', [
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'status' => 'draft',
        ]);
    }

    public function test_manager_cannot_create_shift_for_different_branch(): void
    {
        $branchA = $this->createBranch();
        $branchB = $this->createBranch();
        $manager = $this->actingAsManager($branchA);
        $employeeB = $this->createUser(['branch_id' => $branchB->id]);

        $response = $this->post('/shifts', [
            'user_id' => $employeeB->id,
            'branch_id' => $branchB->id,
            'date' => '2026-11-10',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $response->assertForbidden();
    }

    public function test_manager_can_update_draft_shift_for_their_own_branch(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-11-10 08:00:00',
            'end_at' => '2026-11-10 16:00:00',
            'status' => 'draft',
            'version' => 1,
        ]);

        $response = $this->post("/shifts/{$shift->id}/update", [
            'date' => '2026-11-10',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'reason' => 'Penyesuaian jam operasional',
            'expected_version' => 1,
        ]);

        $response->assertRedirect();
        $this->assertSame('2026-11-10 09:00:00', $shift->fresh()->start_at->toDateTimeString());
    }

    public function test_inactive_user_can_logout_safely(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser(['branch_id' => $branch->id, 'active' => false]);
        $this->actingAs($user);

        $response = $this->post('/logout');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_qr_code_with_previous_time_slot_is_accepted(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id]);
        $this->actingAs($employee);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => now('Asia/Jakarta')->subMinutes(5)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addHours(8)->toDateTimeString(),
            'status' => 'approved',
        ]);

        // Generate QR code for the previous 30s slot (simulating network latency)
        $previousSlot = intdiv(now()->timestamp, 30) - 1;
        $prevQr = AttendanceService::qr($branch, $previousSlot);

        session(['attendance_challenge' => 'test-challenge']);

        $service = new AttendanceService();
        $service->act($employee, $shift->id, 'in', [
            'action' => 'in',
            'qr_code' => $prevQr,
            'challenge' => 'test-challenge',
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 15,
        ], 'test-device-token');

        $this->assertDatabaseHas('attendances', [
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'status' => 'present',
        ]);
    }

    public function test_geofence_with_indoor_accuracy_and_valid_distance_is_not_rejected(): void
    {
        // Branch radius is 50m.
        // User is at distance 10m, but GPS accuracy is 45m (indoors).
        // Under old formula (distance + accuracy = 10 + 45 = 55 > 50), it would be rejected.
        // Under fixed formula, distance <= 50 and accuracy <= 100, so it is accepted.
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 50]);
        $employee = $this->createUser(['branch_id' => $branch->id]);
        $this->actingAs($employee);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => now('Asia/Jakarta')->subMinutes(5)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addHours(8)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $currentQr = AttendanceService::qr($branch);
        session(['attendance_challenge' => 'test-challenge']);

        $service = new AttendanceService();
        $service->act($employee, $shift->id, 'in', [
            'action' => 'in',
            'qr_code' => $currentQr,
            'challenge' => 'test-challenge',
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 45, // 45m accuracy
        ], 'test-device-token-2');

        $this->assertDatabaseHas('attendances', [
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
        ]);
    }

    public function test_manager_cannot_approve_their_own_overtime(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);

        $shift = $this->createShift($manager, $branch, [
            'start_at' => '2026-11-10 08:00:00',
            'end_at' => '2026-11-10 16:00:00',
            'status' => 'approved',
        ]);

        $attendance = Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $manager->id,
            'checkin_at' => '2026-11-10 08:00:00',
            'checkout_at' => '2026-11-10 19:00:00',
            'status' => 'present',
            'overtime_minutes' => 90,
            'overtime_approved_by' => null,
        ]);

        $response = $this->post("/attendance-records/{$attendance->id}/overtime", [
            'reason' => 'Lembur pekerjaan managerial',
        ]);

        $response->assertForbidden();
        $this->assertNull($attendance->fresh()->overtime_approved_by);
    }

    public function test_manager_cannot_correct_their_own_attendance(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);

        $shift = $this->createShift($manager, $branch, [
            'start_at' => '2026-11-10 08:00:00',
            'end_at' => '2026-11-10 16:00:00',
            'status' => 'approved',
        ]);

        $attendance = Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $manager->id,
            'checkin_at' => '2026-11-10 09:30:00',
            'status' => 'late',
            'late_minutes' => 90,
            'late_units' => 5,
        ]);

        $response = $this->post("/attendance-records/{$attendance->id}/correct", [
            'status' => 'present',
            'checkin_at' => '2026-11-10 08:00:00',
            'checkout_at' => '2026-11-10 16:00:00',
            'reason' => 'Koreksi diri sendiri agar tidak kena denda',
        ]);

        $response->assertForbidden();
        $this->assertSame('late', $attendance->fresh()->status);
    }

    public function test_sick_leave_with_medical_certificate_is_fully_paid(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);
        $manager = $this->actingAsManager($branch);

        $dates = ['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05']; // 4 days sick

        $leave = LeaveRequest::create([
            'user_id' => $employee->id,
            'created_by' => $employee->id,
            'type' => 'sick',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-05',
            'reason' => 'Demam Tifoid Rawat Inap',
            'certificate_path' => 'certificates/surat-sakit-rs.pdf',
            'certificate_name' => 'surat-sakit-rs.pdf',
            'status' => 'pending',
        ]);

        foreach ($dates as $d) {
            LeaveDay::create([
                'leave_request_id' => $leave->id,
                'date' => $d,
                'status' => 'pending',
                'paid' => false,
            ]);
        }

        $service = new LeaveService();
        $service->review($leave, $manager, $dates, [], 'Surat keterangan dokter dari RS terverifikasi valid.');

        $this->assertSame('approved', $leave->fresh()->status);
        // All 4 days must be marked paid = true (under UU Ketenagakerjaan Pasal 93)
        $paidCount = LeaveDay::where('leave_request_id', $leave->id)->where('paid', true)->count();
        $this->assertSame(4, $paidCount);
    }

    public function test_overtime_pay_calculated_using_statutory_173_and_tiered_multipliers(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'hired_at' => '2026-10-01',
            'ended_at' => '2026-10-31',
            'base_salary' => 3460000, // 3,460,000 / 173 = exactly 20,000 per hour
        ]);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-10-05 08:00:00',
            'end_at' => '2026-10-05 16:00:00',
            'status' => 'approved',
        ]);

        // 120 minutes overtime (2 hours):
        // 1st hour = 1.5x = 1.5 hours
        // 2nd hour = 2.0x = 2.0 hours
        // Total weighted hours = 3.5 hours
        // Expected overtime pay = 3.5 * 20,000 = 70,000
        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'checkin_at' => '2026-10-05 08:00:00',
            'checkout_at' => '2026-10-05 18:00:00',
            'status' => 'present',
            'late_minutes' => 0,
            'late_units' => 0,
            'overtime_minutes' => 120,
            'overtime_approved_by' => $manager->id,
        ]);

        $calculator = new PayrollCalculator();
        $result = $calculator->calculate($employee, '2026-10');

        $this->assertSame(20000.0, $result['hourly_rate']);
        $this->assertSame(120, $result['overtime_minutes']);
        $this->assertSame(70000, $result['overtime_pay']);
    }

    public function test_full_month_employed_receives_exact_base_salary_without_proration_distortion(): void
    {
        $branch = $this->createBranch();
        // October has 31 days
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'hired_at' => '2026-01-01',
            'ended_at' => null,
            'base_salary' => 6000000,
        ]);

        $calculator = new PayrollCalculator();
        $result = $calculator->calculate($employee, '2026-10');

        $this->assertSame(31, $result['calendar_days']);
        $this->assertSame(31, $result['employed_days']);
        // Base salary must be exactly 6,000,000, not prorated with rounding error
        $this->assertSame(6000000, $result['prorated_base']);
    }

    public function test_manager_can_cancel_approved_leave_for_their_branch(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);
        $manager = $this->actingAsManager($branch);

        $targetDate = now('Asia/Jakarta')->addDays(5)->toDateString();
        $leave = LeaveRequest::create([
            'user_id' => $employee->id,
            'created_by' => $employee->id,
            'type' => 'leave',
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'reason' => 'Perjalanan dinas mendadak',
            'status' => 'approved',
        ]);
        LeaveDay::create([
            'leave_request_id' => $leave->id,
            'date' => $targetDate,
            'status' => 'approved',
            'paid' => true,
        ]);

        $response = $this->post("/leave/{$leave->id}/delete");
        $response->assertRedirect('/leave');
        $this->assertNull(LeaveRequest::find($leave->id));
    }
}

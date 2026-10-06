<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Branch;
use App\Models\Position;
use App\Models\User;
use App\Models\Shift;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\AuditLog;
use App\Services\ShiftService;
use App\Services\LeaveService;
use App\Services\AttendanceService;
use App\Services\PayrollService;
use App\Services\PayrollCalculator;
use Illuminate\Support\Facades\DB;

function verify(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("VERIFICATION FAILED: {$message}");
    }
}

echo "Starting Backend Services & Eloquent Integration Verification...\n";

DB::beginTransaction();

try {
    // 1. Setup Branch, Position & Users
    $branch = Branch::create([
        'code' => 'TEST01',
        'name' => 'Branch Test',
        'latitude' => -6.175392,
        'longitude' => 106.827153,
        'radius_m' => 100,
        'qr_secret' => 'test-secret',
    ]);
    verify($branch->id > 0, 'Branch created');

    $position = Position::create([
        'name' => 'Software Engineer',
    ]);
    verify($position->id > 0, 'Position created');

    $manager = User::create([
        'name' => 'Manager One',
        'email' => 'manager1@example.test',
        'password' => bcrypt('password'),
        'role' => 'manager',
        'branch_id' => $branch->id,
        'position_id' => $position->id,
        'hired_at' => '2026-01-01',
        'base_salary' => 12000000,
        'active' => true,
    ]);

    $employee = User::create([
        'name' => 'Employee One',
        'email' => 'employee1@example.test',
        'password' => bcrypt('password'),
        'role' => 'employee',
        'branch_id' => $branch->id,
        'position_id' => $position->id,
        'hired_at' => '2026-01-01',
        'base_salary' => 8000000,
        'active' => true,
    ]);

    verify($employee->branch->id === $branch->id, 'User branch relationship works');
    verify($employee->position->id === $position->id, 'User position relationship works');

    // 2. ShiftService
    $shiftService = new ShiftService();
    $shift = $shiftService->create([
        'user_id' => $employee->id,
        'branch_id' => $branch->id,
        'date' => '2026-09-15',
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    verify($shift->status === 'draft', 'New shift is in draft status');
    verify($shift->version === 1, 'Shift initial version is 1');

    $updatedShift = $shiftService->update($shift, [
        'date' => '2026-09-15',
        'start_time' => '08:30',
        'end_time' => '17:00',
        'expected_version' => 1,
    ]);
    verify($updatedShift->version === 2, 'Shift version incremented on update');

    $shiftService->approve($updatedShift, $manager);
    verify($updatedShift->fresh()->status === 'approved', 'Shift approved successfully');

    // 3. LeaveService
    auth()->login($employee);
    $leaveService = new LeaveService();
    $leave = $leaveService->submit($employee, [
        'type' => 'leave',
        'start_date' => '2026-09-20',
        'end_date' => '2026-09-21',
        'reason' => 'Family event for two consecutive days',
    ]);

    verify($leave->status === 'pending', 'Leave submission pending');
    verify($leave->days()->count() === 2, 'Leave days generated correctly');

    auth()->login($manager);
    $leaveService->review($leave, $manager, ['2026-09-20', '2026-09-21'], ['2026-09-20'], 'Approved by manager');
    verify($leave->fresh()->status === 'approved', 'Leave request approved');
    verify($leave->days()->where('status', 'approved')->count() === 2, 'Leave days status updated');

    // 4. AttendanceService
    $attendanceService = new AttendanceService();
    $attendance = Attendance::create([
        'shift_id' => $shift->id,
        'user_id' => $employee->id,
        'checkin_at' => '2026-09-15 08:45:00',
        'checkout_at' => '2026-09-15 18:00:00',
        'status' => 'present',
        'late_minutes' => 0,
        'late_units' => 0,
        'overtime_minutes' => 60,
    ]);

    $attendanceService->approveOvertime($attendance, $manager, 'Disetujui manajer');
    verify($attendance->fresh()->overtime_approved_by === $manager->id, 'Overtime approved successfully');

    // 5. PayrollService
    $payrollService = new PayrollService();
    $calculator = new PayrollCalculator();

    $payrollRun = $payrollService->generate($branch->id, '2026-09', $calculator);
    verify($payrollRun->status === 'draft', 'Payroll generated in draft mode');
    verify($payrollRun->lines()->count() >= 2, 'Payroll lines generated for employees');

    $adjustment = $payrollService->addAdjustment([
        'user_id' => $employee->id,
        'month' => '2026-09',
        'amount' => 250000,
        'reason' => 'Bonus penyesuaian performa proyek',
    ], $manager);
    verify($adjustment->amount === 250000, 'Adjustment added successfully');

    // Regenerate payroll with adjustment
    $payrollRun = $payrollService->generate($branch->id, '2026-09', $calculator);
    $employeeLine = $payrollRun->lines()->where('user_id', $employee->id)->first();
    verify($employeeLine !== null, 'Employee payroll line exists');
    $breakdown = is_array($employeeLine->breakdown) ? $employeeLine->breakdown : json_decode($employeeLine->breakdown, true);
    verify($breakdown['manual_total'] === 250000, 'Adjustment reflected in payroll line');

    // Check blockers logic
    $blockers = $payrollService->blockers($branch->id, '2026-09');
    verify(is_array($blockers), 'Payroll blockers returned as array');

    // 6. AuditLog Verification
    $auditLogs = AuditLog::where('actor_id', $manager->id)->get();
    verify($auditLogs->count() > 0, 'Audit logs recorded for manager actions');

    echo "ALL 6 BACKEND SERVICES & ELOQUENT TESTS PASSED SUCCESSFULLY!\n";
} finally {
    DB::rollBack();
}

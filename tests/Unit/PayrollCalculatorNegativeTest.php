<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\PayrollAdjustment;
use App\Services\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalculatorNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_resigned_before_target_month_yields_zero_prorated_base(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'hired_at' => '2026-01-01',
            'ended_at' => '2026-08-31', // Resigned before September
            'base_salary' => 6000000,
        ]);

        $calculator = new PayrollCalculator();
        $result = $calculator->calculate($employee, '2026-09');

        $this->assertSame(0, $result['employed_days']);
        $this->assertSame(0, $result['prorated_base']);
        $this->assertSame(0, $result['net']);
    }

    public function test_employee_hired_after_target_month_yields_zero_prorated_base(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'hired_at' => '2026-10-01', // Hired after September
            'ended_at' => null,
            'base_salary' => 6000000,
        ]);

        $calculator = new PayrollCalculator();
        $result = $calculator->calculate($employee, '2026-09');

        $this->assertSame(0, $result['employed_days']);
        $this->assertSame(0, $result['prorated_base']);
        $this->assertSame(0, $result['net']);
    }

    public function test_zero_base_salary_does_not_cause_division_by_zero(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'hired_at' => '2026-09-01',
            'ended_at' => null,
            'base_salary' => 0,
        ]);

        $calculator = new PayrollCalculator();
        $result = $calculator->calculate($employee, '2026-09');

        $this->assertSame(0.0, $result['daily_rate']);
        $this->assertSame(0.0, $result['hourly_rate']);
        $this->assertSame(0, $result['prorated_base']);
        $this->assertSame(0, $result['net']);
    }

    public function test_unapproved_overtime_is_strictly_not_paid(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'hired_at' => '2026-09-01',
            'ended_at' => '2026-09-30',
            'base_salary' => 3000000,
        ]);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => '2026-09-10 09:00:00',
            'end_at' => '2026-09-10 17:00:00',
            'status' => 'approved',
        ]);

        // Attendance with overtime minutes but overtime_approved_by is NULL
        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'checkin_at' => '2026-09-10 09:00:00',
            'checkout_at' => '2026-09-10 20:00:00',
            'status' => 'present',
            'late_minutes' => 0,
            'late_units' => 0,
            'overtime_minutes' => 90,
            'overtime_approved_by' => null, // NOT approved
        ]);

        $calculator = new PayrollCalculator();
        $result = $calculator->calculate($employee, '2026-09');

        $this->assertSame(0, $result['overtime_minutes']);
        $this->assertSame(0, $result['overtime_pay']);
    }

    public function test_negative_adjustments_and_heavy_penalties_can_reduce_net_pay(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'hired_at' => '2026-09-20',
            'ended_at' => '2026-09-22', // Employed only 3 days
            'base_salary' => 3000000,   // Daily rate = 100,000 (300,000 prorated base)
        ]);

        // Negative manual deduction (e.g., equipment damage deduction)
        PayrollAdjustment::create([
            'user_id' => $employee->id,
            'month' => '2026-09',
            'amount' => -500000,
            'reason' => 'Potongan ganti rugi inventaris',
            'created_by' => $employee->id,
        ]);

        $calculator = new PayrollCalculator();
        $result = $calculator->calculate($employee, '2026-09');

        $this->assertSame(3, $result['employed_days']);
        $this->assertSame(300000, $result['prorated_base']);
        $this->assertSame(-500000, $result['manual_total']);
        // Net pay = 300,000 - 500,000 = -200,000
        $this->assertSame(-200000, $result['net']);
    }
}

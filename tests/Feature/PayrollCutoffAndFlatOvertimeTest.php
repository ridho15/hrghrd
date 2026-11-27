<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollCalculator;
use App\Services\PayrollService;
use App\Support\PayrollPeriod;
use App\Support\Period;
use App\Support\Rules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Kebijakan HRG: periode payroll 21 s.d. 20 bulan berikutnya (nama = bulan akhir),
 * lembur Rp10.000 per jam penuh.
 */
class PayrollCutoffAndFlatOvertimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2027-01-05 10:00:00');
    }

    private function overtimeAttendance(User $employee, Branch $branch, string $date, int $minutes, User $approver): Attendance
    {
        $shift = $this->createShift($employee, $branch, [
            'start_at' => "{$date} 08:00:00",
            'end_at' => "{$date} 17:00:00",
        ]);

        return Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'checkin_at' => "{$date} 07:55:00",
            'checkout_at' => "{$date} 19:00:00",
            'status' => 'present',
            'late_minutes' => 0,
            'late_units' => 0,
            'overtime_minutes' => $minutes,
            'overtime_approved_by' => $approver->id,
            'overtime_approved_at' => now('Asia/Jakarta'),
        ]);
    }

    public function test_policy_settings_are_applied(): void
    {
        $this->assertSame('fixed_amount', Rules::get('overtime_rate_scheme'));
        $this->assertSame(10000, Rules::int('overtime_rate_per_unit'));
        $this->assertSame(60, Rules::int('overtime_unit_minutes'));
        $this->assertSame('floor', Rules::get('overtime_rounding_mode'));
        $this->assertSame(20, PayrollPeriod::cutoffDay());
    }

    public function test_overtime_is_paid_rp10000_per_full_hour(): void
    {
        $this->assertSame(0, Rules::calculateOvertimePay(59, 5000000)['pay']);
        $this->assertSame(10000, Rules::calculateOvertimePay(60, 5000000)['pay']);
        $this->assertSame(20000, Rules::calculateOvertimePay(150, 5000000)['pay']);
        $this->assertSame(30000, Rules::calculateOvertimePay(180, 9000000)['pay']);
    }

    public function test_period_runs_from_21st_to_20th_named_by_end_month(): void
    {
        $december = PayrollPeriod::nominal('2026-12');
        $this->assertSame('2026-11-21', $december['start']->toDateString());
        $this->assertSame('2026-12-20', $december['end']->toDateString());

        $january = PayrollPeriod::nominal('2027-01');
        $this->assertSame('2026-12-21', $january['start']->toDateString());
        $this->assertSame('2027-01-20', $january['end']->toDateString());

        $march = PayrollPeriod::nominal('2027-03');
        $this->assertSame('2027-02-21', $march['start']->toDateString());
        $this->assertSame('2027-03-20', $march['end']->toDateString());

        $this->assertSame('2026-11', PayrollPeriod::monthFor('2026-11-20'));
        $this->assertSame('2026-12', PayrollPeriod::monthFor('2026-11-21'));
        $this->assertSame('2026-12', PayrollPeriod::monthFor('2026-12-20'));
        $this->assertSame('2027-01', PayrollPeriod::monthFor('2026-12-21'));
    }

    public function test_cutoff_zero_falls_back_to_calendar_month(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'payroll_cutoff_day'], ['value' => '0']);

        $range = PayrollPeriod::nominal('2027-02');
        $this->assertSame('2027-02-01', $range['start']->toDateString());
        $this->assertSame('2027-02-28', $range['end']->toDateString());
        $this->assertSame('2026-11', PayrollPeriod::monthFor('2026-11-25'));
    }

    public function test_calculator_counts_only_activity_inside_the_21_to_20_window(): void
    {
        $branch = $this->createBranch();
        $admin = $this->createUser(['role' => 'admin', 'branch_id' => null]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'base_salary' => 6000000]);

        $this->overtimeAttendance($employee, $branch, '2026-11-20', 120, $admin); // periode November
        $this->overtimeAttendance($employee, $branch, '2026-11-21', 120, $admin); // periode Desember
        $this->overtimeAttendance($employee, $branch, '2026-12-20', 90, $admin);  // periode Desember
        $this->overtimeAttendance($employee, $branch, '2026-12-21', 60, $admin);  // periode Januari

        $december = app(PayrollCalculator::class)->calculate($employee, '2026-12');

        $this->assertSame('2026-11-21', $december['period_start']);
        $this->assertSame('2026-12-20', $december['period_end']);
        $this->assertSame(30, $december['calendar_days']);
        $this->assertSame(2, $december['total_shifts']);
        $this->assertSame(210, $december['overtime_minutes']);
        $this->assertSame(30000, $december['overtime_pay']); // 2 jam + 1 jam (90 menit dibulatkan ke bawah)
        $this->assertSame(6000000, $december['prorated_base']);
        $this->assertSame(6030000, $december['net']);
    }

    public function test_base_salary_is_prorated_over_the_period_for_mid_period_hires(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id, 'base_salary' => 6000000, 'hired_at' => '2026-12-01']);

        $december = app(PayrollCalculator::class)->calculate($employee, '2026-12');

        $this->assertSame(20, $december['employed_days']);
        $this->assertSame(4000000, $december['prorated_base']); // 6.000.000 / 30 x 20
    }

    public function test_first_period_after_a_calendar_month_run_does_not_pay_dates_twice(): void
    {
        $branch = $this->createBranch();
        PayrollRun::create(['branch_id' => $branch->id, 'month' => '2026-10', 'status' => 'locked']);

        $november = PayrollPeriod::range('2026-11', $branch->id);
        $this->assertSame('2026-11-01', $november['start']->toDateString());
        $this->assertSame('2026-11-20', $november['end']->toDateString());

        $employee = $this->createUser(['branch_id' => $branch->id, 'base_salary' => 6000000]);
        $detail = app(PayrollCalculator::class)->calculate($employee, '2026-11');

        $this->assertSame('2026-11-01', $detail['period_start']);
        $this->assertSame(20, $detail['period_days']);
        $this->assertSame(6000000, $detail['prorated_base']);
    }

    public function test_generate_and_approve_store_the_period_and_close_its_dates(): void
    {
        $branch = $this->createBranch();
        $admin = $this->createUser(['role' => 'admin', 'branch_id' => null]);
        $this->createUser(['branch_id' => $branch->id]);
        $service = app(PayrollService::class);
        $calculator = app(PayrollCalculator::class);

        $this->travelTo('2026-12-20 15:00:00');
        $service->generate($branch->id, '2026-12', $calculator);

        try {
            $service->approve($branch->id, '2026-12', $calculator, $admin);
            $this->fail('Persetujuan sebelum tanggal 20 berakhir harus ditolak.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->travelTo('2026-12-21 08:00:00');
        $run = $service->approve($branch->id, '2026-12', $calculator, $admin);

        $this->assertSame('approved', $run->status);
        $this->assertSame('2026-11-21', $run->period_start->toDateString());
        $this->assertSame('2026-12-20', $run->period_end->toDateString());

        Period::writable($branch->id, '2026-12-21');
        Period::writable($branch->id, '2026-11-20');

        $this->expectException(HttpException::class);
        Period::writable($branch->id, '2026-11-25');
    }

    public function test_legacy_calendar_run_still_locks_its_late_dates(): void
    {
        $branch = $this->createBranch();
        PayrollRun::create(['branch_id' => $branch->id, 'month' => '2026-10', 'status' => 'locked']);

        Period::writable($branch->id, '2026-11-02');

        $this->expectException(HttpException::class);
        Period::writable($branch->id, '2026-10-25');
    }

    public function test_admin_can_change_the_cutoff_day(): void
    {
        $this->actingAsAdmin();

        $response = $this->from('/settings')->post('/settings', [
            'late_grace_minutes' => '15',
            'late_unit_minutes' => '15',
            'late_penalty_per_unit' => '10000',
            'late_reject_minutes' => '60',
            'checkin_early_minutes' => '60',
            'checkout_late_hours' => '6',
            'overtime_threshold_minutes' => '90',
            'leave_notice_days' => '7',
            'sick_paid_days_per_case' => '2',
            'hourly_divisor' => '173',
            'daily_divisor' => 'calendar',
            'payroll_cutoff_day' => '25',
        ]);

        $response->assertRedirect('/settings');
        $this->assertSame(25, PayrollPeriod::cutoffDay());

        $this->from('/settings')->post('/settings', ['payroll_cutoff_day' => '31'])
            ->assertSessionHasErrors('payroll_cutoff_day');
    }
}

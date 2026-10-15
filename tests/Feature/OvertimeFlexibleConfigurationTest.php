<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\User;
use App\Services\PayrollCalculator;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OvertimeFlexibleConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function setSetting(string $key, string $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
    }

    public function test_admin_can_save_flexible_overtime_settings_per_minute_fixed_rate(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'late_grace_minutes' => '15',
            'late_unit_minutes' => '15',
            'late_penalty_per_unit' => '10000',
            'late_reject_minutes' => '60',
            'checkin_early_minutes' => '60',
            'checkout_late_hours' => '6',
            'overtime_threshold_minutes' => '30',
            'overtime_calculation_basis' => 'per_minute',
            'overtime_rate_scheme' => 'fixed_amount',
            'overtime_unit_minutes' => '15',
            'overtime_rate_per_unit' => '15000',
            'overtime_max_daily_minutes' => '240',
            'overtime_rounding_mode' => 'floor',
            'leave_notice_days' => '7',
            'sick_paid_days_per_case' => '2',
            'hourly_divisor' => '173',
            'daily_divisor' => 'calendar',
        ];

        $response = $this->from('/settings')->post('/settings', $payload);

        $response->assertRedirect('/settings');
        $response->assertSessionHas('ok');

        $this->assertEquals('per_minute', Rules::get('overtime_calculation_basis'));
        $this->assertEquals('fixed_amount', Rules::get('overtime_rate_scheme'));
        $this->assertEquals(15, Rules::int('overtime_unit_minutes'));
        $this->assertEquals(15000, Rules::int('overtime_rate_per_unit'));
        $this->assertEquals('floor', Rules::get('overtime_rounding_mode'));
        $this->assertEquals(240, Rules::int('overtime_max_daily_minutes'));
    }

    public function test_admin_can_save_settings_per_hour_statutory_pp35(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'late_grace_minutes' => '15',
            'late_unit_minutes' => '15',
            'late_penalty_per_unit' => '10000',
            'late_reject_minutes' => '60',
            'checkin_early_minutes' => '60',
            'checkout_late_hours' => '6',
            'overtime_threshold_minutes' => '60',
            'overtime_calculation_basis' => 'per_hour',
            'overtime_rate_scheme' => 'statutory_pp35',
            'overtime_unit_minutes' => '60',
            'overtime_rate_per_unit' => '25000',
            'overtime_max_daily_minutes' => '180',
            'overtime_rounding_mode' => 'round',
            'leave_notice_days' => '7',
            'sick_paid_days_per_case' => '2',
            'hourly_divisor' => '24',
            'daily_divisor' => 'fixed_30',
        ];

        $response = $this->from('/settings')->post('/settings', $payload);

        $response->assertRedirect('/settings');
        $response->assertSessionHas('ok');
        $this->assertEquals('per_hour', Rules::get('overtime_calculation_basis'));
        $this->assertEquals('statutory_pp35', Rules::get('overtime_rate_scheme'));
        $this->assertEquals(60, Rules::int('overtime_unit_minutes'));
        $this->assertEquals(180, Rules::int('overtime_max_daily_minutes'));
        $this->assertEquals('round', Rules::get('overtime_rounding_mode'));
    }

    public function test_settings_validation_rejects_invalid_overtime_inputs(): void
    {
        $this->actingAsAdmin();

        $basePayload = [
            'late_grace_minutes' => '15',
            'late_unit_minutes' => '15',
            'late_penalty_per_unit' => '10000',
            'late_reject_minutes' => '60',
            'checkin_early_minutes' => '60',
            'checkout_late_hours' => '6',
            'overtime_threshold_minutes' => '30',
            'overtime_calculation_basis' => 'per_minute',
            'overtime_rate_scheme' => 'fixed_amount',
            'overtime_unit_minutes' => '15',
            'overtime_rate_per_unit' => '15000',
            'overtime_max_daily_minutes' => '240',
            'overtime_rounding_mode' => 'floor',
            'leave_notice_days' => '7',
            'sick_paid_days_per_case' => '2',
            'hourly_divisor' => '173',
            'daily_divisor' => 'calendar',
        ];

        // 1. Invalid basis
        $response = $this->post('/settings', array_merge($basePayload, [
            'overtime_calculation_basis' => 'per_century',
        ]));
        $response->assertSessionHasErrors('overtime_calculation_basis');

        // 2. Invalid rate scheme
        $response = $this->post('/settings', array_merge($basePayload, [
            'overtime_rate_scheme' => 'random_scheme',
        ]));
        $response->assertSessionHasErrors('overtime_rate_scheme');

        // 3. Unit minutes <= 0
        $response = $this->post('/settings', array_merge($basePayload, [
            'overtime_unit_minutes' => '0',
        ]));
        $response->assertSessionHasErrors('overtime_unit_minutes');

        // 4. Rate per unit negative
        $response = $this->post('/settings', array_merge($basePayload, [
            'overtime_rate_per_unit' => '-500',
        ]));
        $response->assertSessionHasErrors('overtime_rate_per_unit');

        // 5. Invalid rounding mode
        $response = $this->post('/settings', array_merge($basePayload, [
            'overtime_rounding_mode' => 'truncate',
        ]));
        $response->assertSessionHasErrors('overtime_rounding_mode');
    }

    public function test_non_admin_cannot_update_settings(): void
    {
        $this->actingAsEmployee();

        $response = $this->post('/settings', [
            'overtime_calculation_basis' => 'per_hour',
        ]);

        $response->assertStatus(403);
    }

    public function test_rules_overtime_units_with_different_rounding_modes(): void
    {
        $this->setSetting('overtime_unit_minutes', '15');

        // Test floor mode
        $this->setSetting('overtime_rounding_mode', 'floor');
        $this->assertEquals(0, Rules::overtimeUnits(0));
        $this->assertEquals(0, Rules::overtimeUnits(14));
        $this->assertEquals(1, Rules::overtimeUnits(15));
        $this->assertEquals(2, Rules::overtimeUnits(40)); // 40 / 15 = 2.66 -> 2
        $this->assertEquals(3, Rules::overtimeUnits(45));

        // Test round mode
        $this->setSetting('overtime_rounding_mode', 'round');
        $this->assertEquals(2, Rules::overtimeUnits(37)); // 37 / 15 = 2.46 -> 2
        $this->assertEquals(3, Rules::overtimeUnits(38)); // 38 / 15 = 2.53 -> 3

        // Test ceil mode
        $this->setSetting('overtime_rounding_mode', 'ceil');
        $this->assertEquals(1, Rules::overtimeUnits(1));  // 1 / 15 = 0.06 -> 1
        $this->assertEquals(3, Rules::overtimeUnits(35)); // 35 / 15 = 2.33 -> 3
    }

    public function test_rules_calculate_overtime_pay_fixed_amount_scheme(): void
    {
        $this->setSetting('overtime_calculation_basis', 'per_minute');
        $this->setSetting('overtime_rate_scheme', 'fixed_amount');
        $this->setSetting('overtime_unit_minutes', '15');
        $this->setSetting('overtime_rate_per_unit', '15000');
        $this->setSetting('overtime_rounding_mode', 'floor');

        // 45 minutes = 3 units * 15.000 = Rp 45.000
        $result = Rules::calculateOvertimePay(45, 5000000);
        $this->assertEquals(45000, $result['pay']);
        $this->assertEquals(3, $result['units']);
        $this->assertEquals(15, $result['unit_minutes']);
        $this->assertEquals('fixed_amount', $result['scheme']);
        $this->assertStringContainsString('3 unit (45 mnt @ 15 mnt) × Rp 15.000', $result['description']);

        // 50 minutes with floor = 3 units * 15.000 = Rp 45.000
        $resultFloor = Rules::calculateOvertimePay(50, 5000000);
        $this->assertEquals(45000, $resultFloor['pay']);
        $this->assertEquals(3, $resultFloor['units']);
    }

    public function test_rules_calculate_overtime_pay_hourly_prorata_scheme(): void
    {
        $this->setSetting('overtime_calculation_basis', 'per_minute');
        $this->setSetting('overtime_rate_scheme', 'hourly_prorata');
        $this->setSetting('overtime_unit_minutes', '15');
        $this->setSetting('overtime_rounding_mode', 'floor');
        $this->setSetting('hourly_divisor', '173');

        // Monthly salary: 5.190.000 -> hourly rate = 5.190.000 / 173 = 30.000/jam -> 500/menit
        // 1 unit (15 menit) = 15 * 500 = 7.500
        // 45 menit = 3 unit * 7.500 = 22.500
        $result = Rules::calculateOvertimePay(45, 5190000);
        $this->assertEquals(22500, $result['pay']);
        $this->assertEquals(3, $result['units']);
        $this->assertEquals('hourly_prorata', $result['scheme']);
    }

    public function test_rules_calculate_overtime_pay_statutory_pp35_scheme(): void
    {
        $this->setSetting('overtime_rate_scheme', 'statutory_pp35');
        $this->setSetting('hourly_divisor', '173');

        // Base salary = 5.190.000 -> hourly rate = 30.000
        // 60 minutes (1 hr) -> 1.0 * 1.5 = 1.5 weighted hrs * 30.000 = 45.000
        $result1 = Rules::calculateOvertimePay(60, 5190000);
        $this->assertEquals(45000, $result1['pay']);
        $this->assertEquals(1.5, $result1['weighted_hours']);

        // 120 minutes (2 hrs) -> (1.0 * 1.5) + (1.0 * 2.0) = 3.5 weighted hrs * 30.000 = 105.000
        $result2 = Rules::calculateOvertimePay(120, 5190000);
        $this->assertEquals(105000, $result2['pay']);
        $this->assertEquals(3.5, $result2['weighted_hours']);
    }

    public function test_payroll_calculator_uses_flexible_overtime_settings(): void
    {
        $branch = $this->createBranch();
        $admin = $this->createUser(['role' => 'admin']);
        $employee = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'base_salary' => 6000000,
            'hired_at' => '2026-01-01',
        ]);

        // Configure flexible fixed amount overtime: 15 min unit @ Rp 20.000
        $this->setSetting('overtime_rate_scheme', 'fixed_amount');
        $this->setSetting('overtime_unit_minutes', '15');
        $this->setSetting('overtime_rate_per_unit', '20000');
        $this->setSetting('overtime_max_daily_minutes', '240');
        $this->setSetting('overtime_rounding_mode', 'floor');

        $month = '2026-08';
        $shiftDate = '2026-08-10';

        $shift = Shift::create([
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'start_at' => "{$shiftDate} 08:00:00",
            'end_at' => "{$shiftDate} 17:00:00",
            'status' => 'approved',
        ]);

        // 45 minutes overtime, approved by admin
        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'checkin_at' => "{$shiftDate} 07:55:00",
            'checkout_at' => "{$shiftDate} 17:45:00",
            'status' => 'present',
            'late_minutes' => 0,
            'late_units' => 0,
            'overtime_minutes' => 45,
            'overtime_approved_by' => $admin->id,
            'overtime_approved_at' => now('Asia/Jakarta'),
        ]);

        $payroll = app(PayrollCalculator::class)->calculate($employee, $month);

        // 45 minutes / 15 mins = 3 units * Rp 20.000 = Rp 60.000
        $this->assertEquals(45, $payroll['overtime_minutes']);
        $this->assertEquals(3, $payroll['overtime_units']);
        $this->assertEquals(60000, $payroll['overtime_pay']);
        $this->assertEquals('fixed_amount', $payroll['overtime_scheme']);
        $this->assertEquals(15, $payroll['overtime_unit_minutes']);
        $this->assertEquals(20000, $payroll['overtime_rate_per_unit']);
        $this->assertCount(1, $payroll['overtime_sources']);
        $this->assertEquals(3, $payroll['overtime_sources'][0]['units']);
        $this->assertEquals(60000, $payroll['overtime_sources'][0]['pay']);
    }

    public function test_payroll_calculator_ignores_unapproved_overtime(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'base_salary' => 5000000,
            'hired_at' => '2026-01-01',
        ]);

        $this->setSetting('overtime_rate_scheme', 'fixed_amount');
        $this->setSetting('overtime_unit_minutes', '15');
        $this->setSetting('overtime_rate_per_unit', '20000');

        $shiftDate = '2026-08-11';
        $shift = Shift::create([
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'start_at' => "{$shiftDate} 08:00:00",
            'end_at' => "{$shiftDate} 17:00:00",
            'status' => 'approved',
        ]);

        // Overtime 60 mins BUT NOT approved (overtime_approved_by = null)
        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'checkin_at' => "{$shiftDate} 08:00:00",
            'checkout_at' => "{$shiftDate} 18:00:00",
            'status' => 'present',
            'late_minutes' => 0,
            'late_units' => 0,
            'overtime_minutes' => 60,
            'overtime_approved_by' => null,
        ]);

        $payroll = app(PayrollCalculator::class)->calculate($employee, '2026-08');

        $this->assertEquals(0, $payroll['overtime_minutes']);
        $this->assertEquals(0, $payroll['overtime_units']);
        $this->assertEquals(0, $payroll['overtime_pay']);
        $this->assertEmpty($payroll['overtime_sources']);
    }

    public function test_payroll_calculator_caps_overtime_at_max_daily_minutes(): void
    {
        $branch = $this->createBranch();
        $admin = $this->createUser(['role' => 'admin']);
        $employee = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'base_salary' => 5000000,
            'hired_at' => '2026-01-01',
        ]);

        // Max daily = 120 minutes
        $this->setSetting('overtime_max_daily_minutes', '120');
        $this->setSetting('overtime_rate_scheme', 'fixed_amount');
        $this->setSetting('overtime_unit_minutes', '60');
        $this->setSetting('overtime_rate_per_unit', '50000');

        $shiftDate = '2026-08-12';
        $shift = Shift::create([
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'start_at' => "{$shiftDate} 08:00:00",
            'end_at' => "{$shiftDate} 17:00:00",
            'status' => 'approved',
        ]);

        // Recorded 300 minutes overtime
        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'checkin_at' => "{$shiftDate} 08:00:00",
            'checkout_at' => "{$shiftDate} 22:00:00",
            'status' => 'present',
            'late_minutes' => 0,
            'late_units' => 0,
            'overtime_minutes' => 300,
            'overtime_approved_by' => $admin->id,
            'overtime_approved_at' => now('Asia/Jakarta'),
        ]);

        $payroll = app(PayrollCalculator::class)->calculate($employee, '2026-08');

        // 300 capped to 120 -> 120 / 60 = 2 units * 50.000 = Rp 100.000
        $this->assertEquals(120, $payroll['overtime_minutes']);
        $this->assertEquals(2, $payroll['overtime_units']);
        $this->assertEquals(100000, $payroll['overtime_pay']);
    }

    public function test_payroll_slip_and_index_views_render_with_custom_overtime_ratio(): void
    {
        $branch = $this->createBranch();
        $admin = $this->actingAsAdmin();
        $employee = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'base_salary' => 5000000,
            'hired_at' => '2026-01-01',
        ]);

        $this->setSetting('overtime_rate_scheme', 'fixed_amount');
        $this->setSetting('overtime_unit_minutes', '15');
        $this->setSetting('overtime_rate_per_unit', '15000');
        $this->setSetting('overtime_max_daily_minutes', '240');

        $month = '2026-08';
        $shiftDate = '2026-08-15';

        $shift = Shift::create([
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'start_at' => "{$shiftDate} 08:00:00",
            'end_at' => "{$shiftDate} 17:00:00",
            'status' => 'approved',
        ]);

        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'checkin_at' => "{$shiftDate} 08:00:00",
            'checkout_at' => "{$shiftDate} 17:45:00",
            'status' => 'present',
            'overtime_minutes' => 45,
            'overtime_approved_by' => $admin->id,
            'overtime_approved_at' => now('Asia/Jakarta'),
        ]);

        // Generate draft run
        $this->post('/payroll/generate', [
            'branch_id' => $branch->id,
            'month' => $month,
        ]);

        // View payroll index
        $indexResponse = $this->get("/payroll?branch_id={$branch->id}&month={$month}");
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('3 Unit');

        // View employee slip
        $slipResponse = $this->get("/payroll/slip/{$employee->id}?branch_id={$branch->id}&month={$month}");
        $slipResponse->assertStatus(200);
        $slipResponse->assertSee('3 Unit (45 Menit @ 15 mnt)');
        $slipResponse->assertSee('15.000');
        $slipResponse->assertSee('+ Rp45.000');
    }
}

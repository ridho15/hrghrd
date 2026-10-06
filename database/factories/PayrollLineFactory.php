<?php

namespace Database\Factories;

use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollLine>
 */
class PayrollLineFactory extends Factory
{
    protected $model = PayrollLine::class;

    public function definition(): array
    {
        $base = 3000000;
        return [
            'payroll_run_id' => PayrollRun::factory(),
            'user_id' => User::factory(),
            'breakdown' => [
                'monthly_salary' => $base,
                'calendar_days' => 30,
                'daily_divisor' => 30,
                'employed_days' => 30,
                'daily_rate' => 100000.0,
                'hourly_rate' => 4166.67,
                'prorated_base' => $base,
                'unpaid_dates' => [],
                'unpaid_deduction' => 0,
                'late_units' => 0,
                'late_sources' => [],
                'late_deduction' => 0,
                'overtime_minutes' => 0,
                'overtime_sources' => [],
                'overtime_pay' => 0,
                'adjustments' => [],
                'manual_total' => 0,
                'net' => $base,
            ],
            'net' => $base,
        ];
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class Rules
{
    public const DEFAULTS = [
        'late_grace_minutes' => '15', 'late_unit_minutes' => '15',
        'late_penalty_per_unit' => '10000', 'late_reject_minutes' => '60',
        'checkin_early_minutes' => '60', 'checkout_late_hours' => '6',
        'overtime_threshold_minutes' => '90', 'overtime_max_daily_minutes' => '240',
        'min_work_duration_minutes' => '30',
        'leave_notice_days' => '7',
        'sick_paid_days_per_case' => '2', 'daily_divisor' => 'calendar',
        'hourly_divisor' => '24',
    ];

    public static function get(string $key): string
    {
        return (string) (DB::table('settings')->where('key', $key)->value('value') ?? self::DEFAULTS[$key]);
    }

    public static function int(string $key): int { return (int) self::get($key); }

    public static function lateUnits(int $minutes): int
    {
        if ($minutes <= self::int('late_grace_minutes')) return 0;
        return (int) ceil(($minutes - self::int('late_grace_minutes')) / max(1, self::int('late_unit_minutes')));
    }
}

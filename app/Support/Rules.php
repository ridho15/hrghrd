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
        'overtime_calculation_basis' => 'per_hour', // 'per_hour' atau 'per_minute'
        'overtime_rate_scheme' => 'statutory_pp35', // 'fixed_amount', 'hourly_prorata', 'statutory_pp35'
        'overtime_unit_minutes' => '60', // Durasi per 1 unit lembur (15, 30, 60 menit)
        'overtime_rate_per_unit' => '20000', // Nominal rupiah per unit (skema fixed_amount)
        'overtime_rounding_mode' => 'floor', // 'floor', 'round', 'ceil'
        'min_work_duration_minutes' => '30',
        'leave_notice_days' => '7',
        'sick_paid_days_per_case' => '2', 'daily_divisor' => 'calendar',
        'hourly_divisor' => '24',
    ];

    public static function get(string $key): string
    {
        return (string) (DB::table('settings')->where('key', $key)->value('value') ?? (self::DEFAULTS[$key] ?? ''));
    }

    public static function int(string $key): int { return (int) self::get($key); }

    public static function lateUnits(int $minutes): int
    {
        if ($minutes <= self::int('late_grace_minutes')) return 0;
        return (int) ceil(($minutes - self::int('late_grace_minutes')) / max(1, self::int('late_unit_minutes')));
    }

    public static function overtimeUnits(int $minutes): int
    {
        if ($minutes <= 0) return 0;
        $unit = max(1, self::int('overtime_unit_minutes'));
        $mode = self::get('overtime_rounding_mode');

        return match ($mode) {
            'ceil'  => (int) ceil($minutes / $unit),
            'round' => (int) round($minutes / $unit),
            default => (int) floor($minutes / $unit),
        };
    }

    /**
     * Hitung upah dan rincian lembur berdasarkan konfigurasi aturan aktif.
     *
     * @return array{pay: int, units: int, unit_minutes: int, scheme: string, basis: string, rate_per_unit: int, weighted_hours: float, description: string}
     */
    public static function calculateOvertimePay(int $minutes, float $baseSalary): array
    {
        $basis = self::get('overtime_calculation_basis');
        $scheme = self::get('overtime_rate_scheme');
        $unitMinutes = max(1, self::int('overtime_unit_minutes'));
        $ratePerUnit = max(0, self::int('overtime_rate_per_unit'));
        $units = self::overtimeUnits($minutes);

        // PP 35/2021 statutory hourly rate: 1/173 of monthly salary
        $divisor = self::int('hourly_divisor');
        $hourlyRate = ($baseSalary > 0)
            ? (($divisor === 24 || $divisor === 173)
                ? ((float) $baseSalary / 173)
                : ((float) $baseSalary / max(1, $divisor)))
            : 0.0;

        $pay = 0;
        $weightedHours = 0.0;
        $desc = '';

        if ($scheme === 'fixed_amount') {
            $pay = $units * $ratePerUnit;
            $desc = "{$units} unit ({$minutes} mnt @ {$unitMinutes} mnt) × Rp " . number_format($ratePerUnit, 0, ',', '.');
        } elseif ($scheme === 'hourly_prorata') {
            $minuteRate = $hourlyRate / 60.0;
            $pay = (int) round($units * ($minuteRate * $unitMinutes));
            $unitRateFormatted = number_format($minuteRate * $unitMinutes, 0, ',', '.');
            $desc = "{$units} unit ({$minutes} mnt @ {$unitMinutes} mnt) × Rp {$unitRateFormatted} (proporsional upah)";
        } else {
            // Statutory PP 35/2021 (Tiered 1.5x / 2.0x)
            $hours = $minutes / 60.0;
            if ($hours <= 1.0) {
                $weightedHours = $hours * 1.5;
            } else {
                $weightedHours = (1.0 * 1.5) + (($hours - 1.0) * 2.0);
            }
            $pay = (int) round($hourlyRate * $weightedHours);
            $desc = 'Regulasi PP 35/2021: ' . round($hours, 2) . ' jam kerja (' . round($weightedHours, 2) . ' jam terbobot)';
        }

        return [
            'pay'            => max(0, $pay),
            'units'          => $units,
            'unit_minutes'   => $unitMinutes,
            'scheme'         => $scheme,
            'basis'          => $basis,
            'rate_per_unit'  => $ratePerUnit,
            'weighted_hours' => $weightedHours,
            'description'    => $desc,
        ];
    }
}

<?php

namespace App\Services;

use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class PayrollCalculator
{
    public function calculate(object $employee, string $month): array
    {
        $first = Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta');
        $last = $first->copy()->endOfMonth();
        $days = $first->daysInMonth;
        $hired = $employee->hired_at ? Carbon::parse($employee->hired_at, 'Asia/Jakarta') : $first;
        $employedStart = $hired->greaterThan($first) ? $hired : $first;
        $ended = $employee->ended_at ? Carbon::parse($employee->ended_at, 'Asia/Jakarta') : $last->copy()->startOfDay();
        $employedEnd = $ended->lessThan($last) ? $ended : $last->copy()->startOfDay();
        $employedDays = $employedStart->greaterThan($employedEnd) ? 0 : (int)$employedStart->diffInDays($employedEnd) + 1;
        $dailyDivisor = Rules::get('daily_divisor') === 'fixed_30' ? 30 : $days;
        $daily = (float) $employee->base_salary / $dailyDivisor;
        $hourly = $daily / max(1, Rules::int('hourly_divisor'));

        $leaveDays = DB::table('leave_days as d')->join('leave_requests as r','r.id','=','d.leave_request_id')
            ->where('r.user_id',$employee->id)->where('d.status','approved')
            ->whereBetween('d.date',[$first->toDateString(),$last->toDateString()])
            ->select('d.date','d.paid','r.id as request_id')->get();
        $paidLeave = $leaveDays->where('paid',1)->pluck('date')->all();
        $unpaidDates = $leaveDays->where('paid',0)->pluck('date')->unique()->values()->all();
        $shifts = DB::table('shifts')->where('user_id',$employee->id)->where('status','approved')
            ->whereBetween('start_at',[$first->toDateTimeString(),$last->copy()->endOfDay()->toDateTimeString()])->get();
        $shiftIds = $shifts->pluck('id')->all();
        $attendances = DB::table('attendances')->whereIn('shift_id',$shiftIds ?: [-1])->get()->keyBy('shift_id');
        $missing = [];
        foreach ($shifts as $shift) {
            $date = substr($shift->start_at,0,10);
            if (in_array($date,$paidLeave,true) || in_array($date,$unpaidDates,true)) continue;
            if (Carbon::parse($shift->end_at,'Asia/Jakarta')->isPast() &&
                (!$attendances->get($shift->id) || $attendances->get($shift->id)->status === 'absent')) $missing[] = $date;
        }
        $unpaidDates = array_values(array_unique(array_merge($unpaidDates,$missing)));
        $unpaidDates = array_values(array_filter($unpaidDates, fn ($d) => $d >= $employedStart->toDateString() && $d <= $employedEnd->toDateString()));
        $lateSources = []; $overtimeSources = [];
        foreach ($attendances as $attendance) {
            if ($attendance->status !== 'absent' && $attendance->late_units > 0)
                $lateSources[] = ['attendance_id'=>$attendance->id,'units'=>$attendance->late_units];
            if ($attendance->overtime_approved_by && $attendance->overtime_minutes > 0)
                $overtimeSources[] = ['attendance_id'=>$attendance->id,'minutes'=>$attendance->overtime_minutes];
        }
        $lateUnits = array_sum(array_column($lateSources,'units'));
        $overtimeMinutes = array_sum(array_column($overtimeSources,'minutes'));
        $adjustments = DB::table('payroll_adjustments')->where('user_id',$employee->id)->where('month',$month)->get(['id','amount','reason'])->toArray();
        $manual = array_sum(array_column($adjustments,'amount'));
        $base = (int) round($daily * $employedDays);
        $unpaid = (int) round($daily * count($unpaidDates));
        $late = $lateUnits * Rules::int('late_penalty_per_unit');
        $overtime = (int) round($hourly * $overtimeMinutes / 60);
        return [
            'monthly_salary'=>(int)$employee->base_salary, 'calendar_days'=>$days, 'daily_divisor'=>$dailyDivisor,
            'employed_days'=>$employedDays, 'daily_rate'=>round($daily,2), 'hourly_rate'=>round($hourly,2),
            'prorated_base'=>$base, 'unpaid_dates'=>$unpaidDates, 'unpaid_deduction'=>$unpaid,
            'late_units'=>$lateUnits, 'late_sources'=>$lateSources, 'late_deduction'=>$late,
            'overtime_minutes'=>$overtimeMinutes, 'overtime_sources'=>$overtimeSources, 'overtime_pay'=>$overtime,
            'adjustments'=>$adjustments, 'manual_total'=>$manual,
            'net'=>$base-$unpaid-$late+$overtime+$manual,
        ];
    }
}

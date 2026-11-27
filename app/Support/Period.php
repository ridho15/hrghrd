<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class Period
{
    public static function writable(int $branchId, string $date): void
    {
        $day = substr($date, 0, 10);
        // Cek periode menurut cut-off dan bulan kalender (periode lama sebelum cut-off berlaku).
        $months = array_unique([PayrollPeriod::monthFor($day), substr($day, 0, 7)]);
        $runs = DB::table('payroll_runs')->where('branch_id', $branchId)
            ->whereIn('month', $months)->whereIn('status', ['approved', 'locked'])->get();

        foreach ($runs as $run) {
            $range = PayrollPeriod::stored($run);
            $closed = $day >= $range['start']->toDateString() && $day <= $range['end']->toDateString();
            abort_if($closed, 409, 'Periode payroll sudah disetujui atau dikunci.');
        }
    }
}

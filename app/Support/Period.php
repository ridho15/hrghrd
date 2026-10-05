<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class Period
{
    public static function writable(int $branchId, string $date): void
    {
        $closed = DB::table('payroll_runs')->where('branch_id',$branchId)
            ->where('month',substr($date,0,7))->whereIn('status',['approved','locked'])->exists();
        abort_if($closed, 409, 'Periode payroll sudah disetujui atau dikunci.');
    }
}

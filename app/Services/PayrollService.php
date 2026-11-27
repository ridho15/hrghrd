<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceException;
use App\Models\LeaveRequest;
use App\Models\PayrollAdjustment;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\User;
use App\Support\Audit;
use App\Support\PayrollPeriod;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function blockers(int $branchId, string $month): array
    {
        $range = PayrollPeriod::range($month, $branchId);
        $first = $range['start']->toDateString();
        $last = $range['end']->toDateString();
        $blockers = [];

        if (User::where('branch_id', $branchId)->whereIn('role', ['employee', 'manager'])
            ->where('active', false)->whereNull('ended_at')->exists()) {
            $blockers[] = 'Karyawan nonaktif tanpa tanggal akhir kerja';
        }

        if (Shift::where('branch_id', $branchId)->where('start_at', '>=', $first)
            ->where('start_at', '<=', $last . ' 23:59:59')->where('status', 'draft')->exists()) {
            $blockers[] = 'Shift draf';
        }

        if (Shift::where('branch_id', $branchId)->where('start_at', '>=', $first)
            ->where('start_at', '<=', $last . ' 23:59:59')->where('end_at', '>', now('Asia/Jakarta'))->exists()) {
            $blockers[] = 'Shift belum selesai';
        }

        if (Attendance::whereHas('shift', function ($q) use ($branchId, $first, $last) {
            $q->where('branch_id', $branchId)->where('start_at', '>=', $first)->where('start_at', '<=', $last . ' 23:59:59');
        })->whereNotNull('checkin_at')->whereNull('checkout_at')->exists()) {
            $blockers[] = 'Absensi belum check-out';
        }

        if (Attendance::whereHas('shift', function ($q) use ($branchId, $first, $last) {
            $q->where('branch_id', $branchId)->where('start_at', '>=', $first)->where('start_at', '<=', $last . ' 23:59:59');
        })->where('overtime_minutes', '>', 0)->whereNull('overtime_approved_by')->exists()) {
            $blockers[] = 'Lembur belum diputuskan';
        }

        if (LeaveRequest::whereHas('user', function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        })->where('start_date', '<=', $last)->where('end_date', '>=', $first)->where('status', 'pending')->exists()) {
            $blockers[] = 'Pengajuan tertunda';
        }

        if (AttendanceException::whereHas('shift', function ($q) use ($branchId, $first, $last) {
            $q->where('branch_id', $branchId)->where('start_at', '>=', $first)->where('start_at', '<=', $last . ' 23:59:59');
        })->where('status', 'pending')->exists()) {
            $blockers[] = 'Pengecualian absensi tertunda';
        }

        return $blockers;
    }

    public function generate(int $branchId, string $month, PayrollCalculator $calculator): PayrollRun
    {
        $run = PayrollRun::where('branch_id', $branchId)->where('month', $month)->first();
        abort_if($run && $run->status !== 'draft', 409, 'Periode sudah disetujui atau dikunci.');

        return DB::transaction(function () use ($branchId, $month, $calculator, $run) {
            $payrollRun = $run ?: PayrollRun::create([
                'branch_id' => $branchId,
                'month' => $month,
                'status' => 'draft',
            ]);

            $range = PayrollPeriod::range($month, $branchId);
            $payrollRun->update([
                'period_start' => $range['start']->toDateString(),
                'period_end' => $range['end']->toDateString(),
            ]);
            $employees = $this->employeesFor($branchId, $month);

            $payrollRun->lines()->delete();

            foreach ($employees as $employee) {
                $detail = $calculator->calculate($employee, $month);
                PayrollLine::create([
                    'payroll_run_id' => $payrollRun->id,
                    'user_id' => $employee->id,
                    'breakdown' => $detail,
                    'net' => $detail['net'],
                ]);
            }

            Audit::record('payroll_run', $payrollRun->id, 'generate', null, null, ['employee_count' => $employees->count()]);

            return $payrollRun;
        });
    }

    public function approve(int $branchId, string $month, PayrollCalculator $calculator, User $approver): PayrollRun
    {
        $run = PayrollRun::where('branch_id', $branchId)->where('month', $month)->first();
        abort_unless($run && $run->status === 'draft', 409, 'Buat draf dulu.');
        $range = PayrollPeriod::range($month, $branchId);
        abort_if(now('Asia/Jakarta')->lte($range['end']->copy()->endOfDay()), 409, 'Periode payroll belum berakhir.');

        $blockers = $this->blockers($branchId, $month);
        abort_if(! empty($blockers), 409, 'Selesaikan shift, pengajuan, dan pengecualian yang tertunda sebelum persetujuan.');

        $saved = $run->lines()->get()->keyBy('user_id');
        $employees = $this->employeesFor($branchId, $month);

        abort_if($saved->count() !== $employees->count(), 409, 'Draf payroll berubah. Buat ulang dan periksa kembali.');

        foreach ($employees as $employee) {
            $expected = json_decode(json_encode($calculator->calculate($employee, $month)), true);
            $actual = $saved->get($employee->id)?->breakdown;
            if (is_string($actual)) {
                $actual = json_decode($actual, true);
            }
            abort_if($actual != $expected, 409, 'Draf payroll berubah. Buat ulang dan periksa kembali.');
        }

        $run->update([
            'period_start' => $range['start']->toDateString(),
            'period_end' => $range['end']->toDateString(),
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        Audit::record('payroll_run', $run->id, 'approve');

        return $run;
    }

    public function lock(int $branchId, string $month, User $locker): PayrollRun
    {
        $run = PayrollRun::where('branch_id', $branchId)->where('month', $month)->first();
        abort_unless($run && $run->status === 'approved', 409, 'Setujui payroll dulu.');

        $last = PayrollPeriod::range($month, $branchId)['end']->copy()->endOfDay();
        abort_if(now('Asia/Jakarta')->lte($last), 409, 'Periode payroll belum berakhir.');
        abort_if(! empty($this->blockers($branchId, $month)), 409, 'Masih ada pekerjaan tertunda.');

        $run->update([
            'status' => 'locked',
            'locked_by' => $locker->id,
            'locked_at' => now(),
        ]);

        Audit::record('payroll_run', $run->id, 'lock');

        return $run;
    }

    /**
     * Karyawan cabang yang bekerja setidaknya satu hari dalam periode payroll.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function employeesFor(int $branchId, string $month): \Illuminate\Database\Eloquent\Collection
    {
        return $this->employeesQuery($branchId, $month)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function employeesQuery(int $branchId, string $month): \Illuminate\Database\Eloquent\Builder
    {
        $range = PayrollPeriod::range($month, $branchId);

        return User::where('branch_id', $branchId)
            ->whereIn('role', ['employee', 'manager'])
            ->where('hired_at', '<=', $range['end']->toDateString())
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $range['start']->toDateString()));
    }

    public function addAdjustment(array $data, User $creator): PayrollAdjustment
    {
        $employee = User::findOrFail($data['user_id']);
        abort_if(PayrollRun::where('branch_id', $employee->branch_id)->where('month', $data['month'])
            ->where('status', '!=', 'draft')->exists(), 409, 'Periode telah disetujui.');

        $adjustment = PayrollAdjustment::create([
            'user_id' => $data['user_id'],
            'month' => $data['month'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'created_by' => $creator->id,
        ]);

        Audit::record('payroll_adjustment', $adjustment->id, 'create', $data['reason'], null, ['amount' => $data['amount']]);

        return $adjustment;
    }
}

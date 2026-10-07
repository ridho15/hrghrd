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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function blockers(int $branchId, string $month): array
    {
        $first = $month . '-01';
        $last = Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta')->endOfMonth()->toDateString();
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

            $end = Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta')->endOfMonth()->toDateString();
            $employees = User::where('branch_id', $branchId)
                ->whereIn('role', ['employee', 'manager'])
                ->where('hired_at', '<=', $end)
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $month . '-01'))
                ->get();

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
        abort_if(now('Asia/Jakarta')->lte(Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta')->endOfMonth()), 409, 'Bulan payroll belum berakhir.');

        $blockers = $this->blockers($branchId, $month);
        abort_if(! empty($blockers), 409, 'Selesaikan shift, pengajuan, dan pengecualian yang tertunda sebelum persetujuan.');

        $saved = $run->lines()->get()->keyBy('user_id');
        $end = Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta')->endOfMonth()->toDateString();
        $employees = User::where('branch_id', $branchId)
            ->whereIn('role', ['employee', 'manager'])
            ->where('hired_at', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $month . '-01'))
            ->get();

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

        $last = Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta')->endOfMonth();
        abort_if(now('Asia/Jakarta')->lte($last), 409, 'Bulan payroll belum berakhir.');
        abort_if(! empty($this->blockers($branchId, $month)), 409, 'Masih ada pekerjaan tertunda.');

        $run->update([
            'status' => 'locked',
            'locked_by' => $locker->id,
            'locked_at' => now(),
        ]);

        Audit::record('payroll_run', $run->id, 'lock');

        return $run;
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

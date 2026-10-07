<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payroll\PayrollAdjustmentRequest;
use App\Http\Requests\Payroll\PayrollSelectionRequest;
use App\Models\Branch;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollCalculator;
use App\Services\PayrollService;
use App\Support\Access;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    private function admin(): void
    {
        abort_unless(Access::admin(), 403);
    }

    public function index(Request $request, PayrollCalculator $calculator, PayrollService $service)
    {
        $this->admin();
        $branches = Branch::orderBy('name')->get();
        $month = $request->query('month', now('Asia/Jakarta')->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 422);
        $branchId = (int) $request->query('branch_id', $branches->first()?->id);
        $branch = $branches->firstWhere('id', $branchId);
        abort_unless($branch, 404);

        $run = PayrollRun::with('lines')->where('branch_id', $branchId)->where('month', $month)->first();
        $end = Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta')->endOfMonth()->toDateString();
        $employeesQuery = User::where('branch_id', $branchId)
            ->whereIn('role', ['employee', 'manager'])
            ->where('hired_at', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $month . '-01'))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $paginatedEmployees = $employeesQuery->paginate(10, ['*'], 'payroll_page')->withQueryString();

        $savedLines = ($run && in_array($run->status, ['approved', 'locked'], true))
            ? $run->lines->keyBy('user_id')
            : collect();

        $lines = [];
        foreach ($paginatedEmployees as $employee) {
            $saved = $savedLines->get($employee->id);
            $detail = null;
            if ($saved) {
                $detail = is_array($saved->breakdown) ? $saved->breakdown : json_decode($saved->breakdown, true);
            } else {
                $detail = $calculator->calculate($employee, $month);
            }
            $lines[] = ['employee' => $employee, 'detail' => $detail];
        }

        $allBranchEmployees = User::where('branch_id', $branchId)->active()->orderBy('name')->get();
        $blockers = $service->blockers($branchId, $month);

        $summary = [
            'total_base' => 0,
            'total_overtime' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'employee_count' => 0,
        ];

        if ($run && in_array($run->status, ['approved', 'locked'], true)) {
            foreach ($run->lines as $lineModel) {
                $d = is_array($lineModel->breakdown) ? $lineModel->breakdown : json_decode($lineModel->breakdown, true);
                $summary['total_base'] += ($d['prorated_base'] ?? 0);
                $summary['total_overtime'] += ($d['overtime_pay'] ?? 0);
                $summary['total_deductions'] += (($d['unpaid_deduction'] ?? 0) + ($d['late_deduction'] ?? 0));
                $summary['total_net'] += ($d['net'] ?? 0);
                $summary['employee_count']++;
            }
        } else {
            $branchAllForSummary = User::where('branch_id', $branchId)
                ->whereIn('role', ['employee', 'manager'])
                ->where('hired_at', '<=', $end)
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $month . '-01'))
                ->get();
            foreach ($branchAllForSummary as $emp) {
                $d = $calculator->calculate($emp, $month);
                $summary['total_base'] += ($d['prorated_base'] ?? 0);
                $summary['total_overtime'] += ($d['overtime_pay'] ?? 0);
                $summary['total_deductions'] += (($d['unpaid_deduction'] ?? 0) + ($d['late_deduction'] ?? 0));
                $summary['total_net'] += ($d['net'] ?? 0);
                $summary['employee_count']++;
            }
        }

        return view('payroll', compact('branches', 'branch', 'branchId', 'month', 'run', 'lines', 'blockers', 'paginatedEmployees', 'allBranchEmployees', 'summary'));
    }

    public function slip(Request $request, int $userId, PayrollCalculator $calculator)
    {
        abort_unless(Access::employee($userId), 403);
        $employee = User::with(['branch', 'position'])->findOrFail($userId);
        $month = $request->query('month', now('Asia/Jakarta')->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 422);

        $branchId = (int) $request->query('branch_id', $employee->branch_id);
        $branch = Branch::findOrFail($branchId);

        $run = PayrollRun::with('lines')->where('branch_id', $branchId)->where('month', $month)->first();
        $detail = null;

        if ($run && in_array($run->status, ['approved', 'locked'], true)) {
            $saved = $run->lines->firstWhere('user_id', $userId);
            if ($saved) {
                $detail = is_array($saved->breakdown) ? $saved->breakdown : json_decode($saved->breakdown, true);
            }
        }

        if (! $detail) {
            $detail = $calculator->calculate($employee, $month);
        }

        return view('payroll-slip', compact('employee', 'branch', 'month', 'run', 'detail'));
    }

    public function generate(PayrollSelectionRequest $request, PayrollCalculator $calculator, PayrollService $service)
    {
        $data = $request->validated();
        $service->generate((int) $data['branch_id'], $data['month'], $calculator);

        return redirect()->route('payroll.index', $data)->with('ok', 'Pratinjau payroll disimpan sebagai draf.');
    }

    public function approve(PayrollSelectionRequest $request, PayrollCalculator $calculator, PayrollService $service)
    {
        $data = $request->validated();
        $service->approve((int) $data['branch_id'], $data['month'], $calculator, auth()->user());

        return back()->with('ok', 'Payroll disetujui.');
    }

    public function lock(PayrollSelectionRequest $request, PayrollService $service)
    {
        $data = $request->validated();
        $service->lock((int) $data['branch_id'], $data['month'], auth()->user());

        return back()->with('ok', 'Payroll dikunci.');
    }

    public function adjustment(PayrollAdjustmentRequest $request, PayrollService $service)
    {
        $data = $request->validated();
        $service->addAdjustment($data, auth()->user());

        return back()->with('ok', 'Koreksi manual ditambahkan. Buat ulang draf.');
    }

    public function reset(PayrollSelectionRequest $request)
    {
        $this->admin();
        $data = $request->validated();
        $run = PayrollRun::where('branch_id', $data['branch_id'])
            ->where('month', $data['month'])
            ->first();

        if (! $run) {
            return back()->with('ok', 'Tidak ada draf payroll yang perlu direset.');
        }

        if ($run->status === 'locked') {
            return back()->withErrors(['payroll' => 'Periode penggajian yang telah dikunci tidak dapat direset.']);
        }

        $runId = $run->id;
        $run->lines()->delete();
        $run->delete();
        \App\Support\Audit::record('payroll', $runId, 'reset', "Draf penggajian cabang ID {$data['branch_id']} periode {$data['month']} direset oleh Super Admin.");

        return back()->with('ok', 'Draf penggajian berhasil direset. Sistem menghitung ulang kalkulasi absensi secara dinamis.');
    }


    public function export(PayrollSelectionRequest $request)
    {
        $data = $request->validated();
        $run = PayrollRun::with(['lines.user.branch', 'lines.user.position'])
            ->where('branch_id', $data['branch_id'])
            ->where('month', $data['month'])
            ->first();

        abort_unless($run && in_array($run->status, ['approved', 'locked'], true), 409);

        $lines = $run->lines->sortBy(fn ($l) => $l->user->name ?? '');

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Nama', 'Email', 'Cabang', 'Jabatan', 'Gaji Pokok / Prorata', 'Potongan Tidak Masuk', 'Potongan Terlambat', 'Upah Lembur', 'Penyesuaian Manual', 'Gaji Bersih (Net)']);
            foreach ($lines as $row) {
                $d = is_array($row->breakdown) ? $row->breakdown : json_decode($row->breakdown, true);
                fputcsv($out, [
                    $row->user_id,
                    $this->safeCsv($row->user->name ?? ''),
                    $this->safeCsv($row->user->email ?? ''),
                    $this->safeCsv($row->user->branch?->name ?? '-'),
                    $this->safeCsv($row->user->position?->name ?? '-'),
                    $d['prorated_base'] ?? 0,
                    $d['unpaid_deduction'] ?? 0,
                    $d['late_deduction'] ?? 0,
                    $d['overtime_pay'] ?? 0,
                    $d['manual_total'] ?? 0,
                    $d['net'] ?? 0,
                ]);
            }
            fclose($out);
        }, 'payroll-' . $data['month'] . '-cabang-' . $data['branch_id'] . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsv(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
    }
}

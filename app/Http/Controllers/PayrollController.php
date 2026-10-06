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

        return view('payroll', compact('branches', 'branch', 'branchId', 'month', 'run', 'lines', 'blockers', 'paginatedEmployees', 'allBranchEmployees'));
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

    public function export(PayrollSelectionRequest $request)
    {
        $data = $request->validated();
        $run = PayrollRun::with(['lines.user'])
            ->where('branch_id', $data['branch_id'])
            ->where('month', $data['month'])
            ->first();

        abort_unless($run && in_array($run->status, ['approved', 'locked'], true), 409);

        $lines = $run->lines->sortBy(fn ($l) => $l->user->name ?? '');

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nama', 'Email', 'Gaji prorata', 'Hari tidak dibayar', 'Potongan absen', 'Lembur', 'Koreksi', 'Jumlah akhir']);
            foreach ($lines as $row) {
                $d = is_array($row->breakdown) ? $row->breakdown : json_decode($row->breakdown, true);
                fputcsv($out, [
                    $this->safeCsv($row->user->name ?? ''),
                    $this->safeCsv($row->user->email ?? ''),
                    $d['prorated_base'],
                    $d['unpaid_deduction'],
                    $d['late_deduction'],
                    $d['overtime_pay'],
                    $d['manual_total'],
                    $d['net'],
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

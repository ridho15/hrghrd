<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollSlipResource;
use App\Models\Branch;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollCalculator;
use App\Services\PayrollService;
use App\Support\Access;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * @group Payroll
 *
 * Endpoint slip gaji karyawan dan ringkasan keuangan cabang.
 */
class PayrollApiController extends Controller
{
    /**
     * Slip Gaji Pribadi
     *
     * Menampilkan slip gaji rincian untuk karyawan yang sedang terautentikasi.
     *
     * Query: `month` (format YYYY-MM, contoh: 2026-10)
     */
    public function mySlip(Request $request, PayrollCalculator $calculator)
    {
        $user = $request->user()->load(['branch', 'position']);
        $month = $request->query('month', now('Asia/Jakarta')->format('Y-m'));

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return response()->json(['success' => false, 'message' => 'Format bulan harus YYYY-MM.'], 422);
        }

        $branch = $user->branch;
        if (! $branch) {
            return response()->json(['success' => false, 'message' => 'Karyawan belum ditugaskan ke cabang.'], 404);
        }

        $run = PayrollRun::with('lines')
            ->where('branch_id', $branch->id)
            ->where('month', $month)
            ->first();

        $detail = null;
        if ($run && in_array($run->status, ['approved', 'locked'], true)) {
            $saved = $run->lines->firstWhere('user_id', $user->id);
            if ($saved) {
                $detail = is_array($saved->breakdown) ? $saved->breakdown : json_decode($saved->breakdown, true);
            }
        }

        if (! $detail) {
            $detail = $calculator->calculate($user, $month);
        }

        return response()->json([
            'success' => true,
            'data'    => new PayrollSlipResource([
                'detail'     => $detail,
                'branch'     => $branch,
                'employee'   => $user,
                'month'      => $month,
                'status'     => $run?->status ?? 'draft',
                'ref_number' => $run ? "PR-{$branch->code}-{$user->id}-" . str_replace('-', '', $month) : null,
            ]),
        ]);
    }

    /**
     * Slip Gaji Karyawan Tertentu
     *
     * Menampilkan slip gaji seorang karyawan.
     * Hanya dapat diakses oleh Manager (untuk cabangnya) atau Admin.
     *
     * Query: `month` (format YYYY-MM)
     */
    public function slip(Request $request, int $userId, PayrollCalculator $calculator)
    {
        if (! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Diperlukan role Manager.'], 403);
        }

        $employee = User::with(['branch', 'position'])->findOrFail($userId);

        if (! Access::admin() && $employee->branch_id !== $request->user()->branch_id) {
            return response()->json(['success' => false, 'message' => 'Karyawan bukan dari cabang Anda.'], 403);
        }

        $month = $request->query('month', now('Asia/Jakarta')->format('Y-m'));

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return response()->json(['success' => false, 'message' => 'Format bulan harus YYYY-MM.'], 422);
        }

        $branch = $employee->branch;
        if (! $branch) {
            return response()->json(['success' => false, 'message' => 'Karyawan tidak memiliki cabang.'], 404);
        }

        $run = PayrollRun::with('lines')
            ->where('branch_id', $branch->id)
            ->where('month', $month)
            ->first();

        $detail = null;
        if ($run && in_array($run->status, ['approved', 'locked'], true)) {
            $saved = $run->lines->firstWhere('user_id', $employee->id);
            if ($saved) {
                $detail = is_array($saved->breakdown) ? $saved->breakdown : json_decode($saved->breakdown, true);
            }
        }

        if (! $detail) {
            $detail = $calculator->calculate($employee, $month);
        }

        return response()->json([
            'success' => true,
            'data'    => new PayrollSlipResource([
                'detail'     => $detail,
                'branch'     => $branch,
                'employee'   => $employee,
                'month'      => $month,
                'status'     => $run?->status ?? 'draft',
                'ref_number' => $run ? "PR-{$branch->code}-{$employee->id}-" . str_replace('-', '', $month) : null,
            ]),
        ]);
    }

    /**
     * Ringkasan Finansial Payroll Cabang
     *
     * Ringkasan total gaji pokok, lembur, potongan, dan take-home pay untuk satu cabang.
     * Hanya dapat diakses oleh Manager dan Admin.
     *
     * Query: `month` (YYYY-MM), `branch_id` (opsional untuk manager, wajib untuk admin jika multi-cabang)
     */
    public function summary(Request $request, PayrollCalculator $calculator, PayrollService $service)
    {
        if (! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Diperlukan role Manager.'], 403);
        }

        $month = $request->query('month', now('Asia/Jakarta')->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return response()->json(['success' => false, 'message' => 'Format bulan harus YYYY-MM.'], 422);
        }

        $branchId = (int) ($request->query('branch_id') ?: $request->user()->branch_id);

        if (! Access::branch($branchId)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses ke cabang ini.'], 403);
        }

        $branch = Branch::findOrFail($branchId);
        $run = PayrollRun::with('lines')->where('branch_id', $branchId)->where('month', $month)->first();
        $end = Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta')->endOfMonth()->toDateString();

        $summary = [
            'total_base'       => 0,
            'total_overtime'   => 0,
            'total_deductions' => 0,
            'total_net'        => 0,
            'employee_count'   => 0,
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
            $branchEmployees = User::where('branch_id', $branchId)
                ->whereIn('role', ['employee', 'manager'])
                ->where('hired_at', '<=', $end)
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $month . '-01'))
                ->get();

            foreach ($branchEmployees as $emp) {
                $d = $calculator->calculate($emp, $month);
                $summary['total_base'] += ($d['prorated_base'] ?? 0);
                $summary['total_overtime'] += ($d['overtime_pay'] ?? 0);
                $summary['total_deductions'] += (($d['unpaid_deduction'] ?? 0) + ($d['late_deduction'] ?? 0));
                $summary['total_net'] += ($d['net'] ?? 0);
                $summary['employee_count']++;
            }
        }

        $blockers = $service->blockers($branchId, $month);

        return response()->json([
            'success' => true,
            'data'    => [
                'branch'         => [
                    'id'   => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ],
                'month'          => $month,
                'status'         => $run?->status ?? 'unprocessed',
                'summary'        => $summary,
                'blockers_count' => count($blockers),
                'blockers'       => $blockers,
            ],
        ]);
    }
}

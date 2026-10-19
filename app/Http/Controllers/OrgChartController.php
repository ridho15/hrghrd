<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\OrgAssignment;
use App\Models\User;
use App\Services\OrgChartService;
use App\Support\Access;
use Illuminate\Http\Request;

class OrgChartController extends Controller
{
    public function __construct(
        protected OrgChartService $orgChartService
    ) {}

    public function index(Request $request)
    {
        abort_unless(Access::canManageOrgChart() || Access::manager(), 403, 'Akses ditolak: Anda tidak memiliki wewenang bagan organisasi.');

        $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;
        $search = trim((string) $request->query('search'));

        $branches = Branch::orderBy('name')->get();
        $employees = User::where('active', true)
            ->where('employment_status', '!=', 'candidate')
            ->orderBy('name')
            ->get();

        $tree = $this->orgChartService->buildTree($branchId, $search);

        return view('org-chart.index', compact('tree', 'branches', 'employees', 'branchId', 'search'));
    }

    public function treeData(Request $request)
    {
        abort_unless(Access::canManageOrgChart() || Access::manager(), 403);

        $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;
        $search = trim((string) $request->query('search'));

        $tree = $this->orgChartService->buildTree($branchId, $search);

        return response()->json($tree);
    }

    public function reassign(Request $request)
    {
        abort_unless(Access::canManageOrgChart(), 403, 'Hanya Admin dan HR yang berwenang mengubah bagan organisasi.');

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'supervisor_id' => 'nullable|exists:users,id',
            'unit' => 'nullable|string|max:100',
            'effective_from' => 'required|date',
            'reason' => 'required|string|min:4|max:255',
        ], [
            'user_id.required' => 'Karyawan yang akan dipindahkan wajib dipilih.',
            'effective_from.required' => 'Tanggal efektif perpindahan wajib diisi.',
            'reason.required' => 'Alasan mutasi / perpindahan atasan wajib diisi.',
        ]);

        $supervisorId = $request->filled('supervisor_id') ? (int) $request->input('supervisor_id') : null;

        $assignment = $this->orgChartService->reassign(
            (int) $validated['user_id'],
            $supervisorId,
            $validated['unit'] ?? null,
            $validated['effective_from'],
            $validated['reason'],
            auth()->id()
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Posisi atasan karyawan dalam bagan organisasi berhasil diperbarui.',
                'assignment' => $assignment,
            ]);
        }

        return back()->with('ok', 'Posisi struktur organisasi karyawan berhasil diperbarui.');
    }

    public function history(int $userId)
    {
        abort_unless(Access::canManageOrgChart() || Access::employee($userId), 403);

        $user = User::with(['branch', 'position'])->findOrFail($userId);
        $history = OrgAssignment::with(['supervisor', 'branch', 'position', 'changedBy'])
            ->where('user_id', $userId)
            ->orderByDesc('effective_from')
            ->get();

        return response()->json([
            'user' => [
                'name' => $user->name,
                'position' => $user->position?->name,
                'branch' => $user->branch?->name,
            ],
            'history' => $history,
        ]);
    }
}

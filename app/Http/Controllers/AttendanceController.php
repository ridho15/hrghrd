<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\AttendanceActRequest;
use App\Http\Requests\Attendance\AttendanceCorrectionRequest;
use App\Http\Requests\Attendance\AttendanceExceptionRequest;
use App\Http\Requests\Attendance\AttendanceExceptionReviewRequest;
use App\Http\Requests\Attendance\AttendanceOvertimeRequest;
use App\Models\Attendance;
use App\Models\AttendanceAttempt;
use App\Models\AttendanceException;
use App\Models\Branch;
use App\Models\Shift;
use App\Services\AttendanceService;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Period;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function act(AttendanceActRequest $request, int $shiftId, AttendanceService $service)
    {
        $data = $request->validated();
        $data['qr_code'] = strtoupper(trim($data['qr_code'] ?? ''));
        $service->act($request->user(), $shiftId, $data['action'], $data, (string) $request->cookie('hrd_device'));

        return back()->with('ok', $data['action'] === 'in' ? 'Check-in tercatat.' : 'Check-out tercatat.');
    }

    public function qrPage(Request $request)
    {
        abort_unless(Access::manager(), 403);
        $branches = Access::admin() ? Branch::orderBy('name')->get() : Branch::where('id', auth()->user()->branch_id)->get();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id')) ?: $branches->first();
        abort_unless($branch, 404);

        return view('qr', compact('branch', 'branches'));
    }

    public function qr(Request $request)
    {
        abort_unless(Access::manager(), 403);
        $branchId = (int) ($request->query('branch_id') ?: auth()->user()->branch_id);
        abort_unless(Access::branch($branchId), 403);
        $branch = Branch::findOrFail($branchId);

        return response()->json(['code' => AttendanceService::qr($branch), 'expires_at' => (intdiv(now()->timestamp, 30) + 1) * 30]);
    }

    public function exception(AttendanceExceptionRequest $request, int $shiftId)
    {
        $data = $request->validated();
        $shift = Shift::where('id', $shiftId)->where('user_id', auth()->id())->first();
        abort_unless($shift && $shift->status === 'approved', 403);
        Period::writable($shift->branch_id, $shift->start_at->toDateString());
        abort_if(AttendanceException::where('shift_id', $shiftId)->where('action', $data['action'])->pending()->exists(), 409, 'Pengecualian sudah menunggu keputusan.');

        $exception = AttendanceException::create([
            'user_id' => auth()->id(),
            'shift_id' => $shiftId,
            'action' => $data['action'],
            'claimed_time' => $data['claimed_time'] ?? null,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);
        Audit::record('attendance_exception', $exception->id, 'request', $data['reason']);

        return back()->with('ok', 'Pengecualian dikirim ke manager. Belum dihitung sebagai absensi.');
    }

    public function review(Request $request)
    {
        abort_unless(Access::manager(), 403);
        $branch = auth()->user()->branch_id;
        $branches = Access::admin() ? Branch::orderBy('name')->get() : Branch::where('id', $branch)->get();

        $exceptions = AttendanceException::with(['user', 'shift'])
            ->whereHas('shift', fn ($q) => Access::admin() ? $q : $q->where('branch_id', $branch))
            ->pending()
            ->orderBy('created_at')
            ->paginate(10, ['*'], 'exceptions_page')
            ->withQueryString();

        $attempts = AttendanceAttempt::with('user')
            ->whereHas('user', fn ($q) => Access::admin() ? $q : $q->where('branch_id', $branch))
            ->when($request->query('search_attempt'), function ($q, $search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(10, ['*'], 'attempts_page')
            ->withQueryString();

        $attendancesQuery = Attendance::with(['user.position', 'user.branch', 'shift.branch', 'overtimeApprover'])
            ->whereHas('shift', function ($q) use ($branch, $request) {
                $q->when(! Access::admin(), fn ($x) => $x->where('branch_id', $branch))
                    ->when($request->query('branch_id') && Access::admin(), fn ($x) => $x->where('branch_id', $request->query('branch_id')))
                    ->when($request->query('date'), fn ($x, $d) => $x->whereDate('start_at', $d));
            })
            ->when($request->query('search'), function ($q, $search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            })
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id');

        $attendances = $attendancesQuery->paginate(10, ['*'], 'attendance_page')->withQueryString();

        return view('attendance-review', compact('exceptions', 'attempts', 'attendances', 'branches'));
    }

    public function reviewException(AttendanceExceptionReviewRequest $request, int $id, AttendanceService $service)
    {
        $data = $request->validated();
        $ex = AttendanceException::findOrFail($id);
        $service->reviewException($ex, auth()->user(), $data['decision'], $data['review_note'], $data['actual_time'] ?? null);

        return back()->with('ok', 'Pengecualian diproses.');
    }

    public function correction(AttendanceCorrectionRequest $request, int $id, AttendanceService $service)
    {
        $data = $request->validated();
        $attendance = Attendance::findOrFail($id);
        $service->correct($attendance, $data);

        return back()->with('ok', 'Koreksi tercatat. Persetujuan lembur direset.');
    }

    public function overtime(AttendanceOvertimeRequest $request, int $id, AttendanceService $service)
    {
        $data = $request->validated();
        $attendance = Attendance::findOrFail($id);
        $service->approveOvertime($attendance, auth()->user(), $data['reason']);

        return back()->with('ok', 'Lembur disetujui.');
    }
}


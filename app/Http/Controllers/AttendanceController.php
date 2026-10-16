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
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function act(AttendanceActRequest $request, int $shiftId, AttendanceService $service)
    {
        $user = $request->user();
        $data = $request->validated();
        $data['qr_code'] = strtoupper(trim($data['qr_code'] ?? ''));

        if ($shiftId === 0 && $user->isFlexibleSchedule()) {
            if (($data['action'] ?? 'in') === 'in') {
                $shift = app(\App\Services\ShiftService::class)->ensureFlexibleShiftOnCheckin($user);
                $shiftId = $shift?->id ?? 0;
            } else {
                $activeAtt = Attendance::where('user_id', $user->id)
                    ->whereNotNull('checkin_at')
                    ->whereNull('checkout_at')
                    ->latest('id')
                    ->first();
                if ($activeAtt) {
                    $shiftId = $activeAtt->shift_id;
                }
            }
        }

        $service->act($user, $shiftId, $data['action'], $data, (string) $request->cookie('hrd_device'));

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

        $interval = AttendanceService::QR_INTERVAL_SECONDS;
        $slot = intdiv(now()->timestamp, $interval);
        $code = AttendanceService::qr($branch, $slot);

        return response()->json([
            'code' => $code,
            'qr_payload' => $code,
            'expires_at' => ($slot + 1) * $interval,
        ]);
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

        $view = in_array($request->query('view'), ['calendar', 'table'], true)
            ? $request->query('view')
            : 'calendar';

        $pendingExceptionsQuery = AttendanceException::whereHas('shift', fn ($q) => Access::admin() ? $q : $q->where('branch_id', $branch))->pending();
        $pendingExceptionsTotal = $pendingExceptionsQuery->count();

        // 1. Data untuk Tab Tabel Log Presensi & Pengecualian
        $exceptions = AttendanceException::with(['user', 'shift'])
            ->whereHas('shift', fn ($q) => Access::admin() ? $q : $q->where('branch_id', $branch))
            ->pending()
            ->orderBy('created_at')
            ->paginate(10, ['*'], 'exceptions_page')
            ->withQueryString();

        $searchAttempt = trim((string) $request->query('search_attempt'));
        $searchAttemptUser = ltrim($searchAttempt, '@');

        $attempts = AttendanceAttempt::with('user')
            ->whereHas('user', fn ($q) => Access::admin() ? $q : $q->where('branch_id', $branch))
            ->when($searchAttempt !== '', function ($q) use ($searchAttempt, $searchAttemptUser) {
                $q->whereHas('user', function ($u) use ($searchAttempt, $searchAttemptUser) {
                    $u->where('name', 'like', "%{$searchAttempt}%")
                        ->orWhere('username', 'like', "%{$searchAttemptUser}%");
                });
            })
            ->latest('id')
            ->paginate(10, ['*'], 'attempts_page')
            ->withQueryString();

        $search = trim((string) $request->query('search'));
        $searchUser = ltrim($search, '@');

        $attendancesQuery = Attendance::with(['user.position', 'user.branch', 'shift.branch', 'overtimeApprover'])
            ->whereHas('shift', function ($q) use ($branch, $request) {
                $q->when(! Access::admin(), fn ($x) => $x->where('branch_id', $branch))
                    ->when($request->query('branch_id') && Access::admin(), fn ($x) => $x->where('branch_id', $request->query('branch_id')))
                    ->when($request->query('date'), fn ($x, $d) => $x->whereDate('start_at', $d));
            })
            ->when($search !== '', function ($q) use ($search, $searchUser) {
                $q->whereHas('user', function ($u) use ($search, $searchUser) {
                    $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$searchUser}%");
                });
            })
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id');

        $attendances = $attendancesQuery->paginate(10, ['*'], 'attendance_page')->withQueryString();

        // 2. Data untuk Tab Kalender Presensi
        $monthInput = (string) $request->query('month', '');
        if (preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
            $currentMonth = Carbon::createFromFormat('Y-m', $monthInput, 'Asia/Jakarta')->startOfMonth();
        } else {
            $currentMonth = now('Asia/Jakarta')->startOfMonth();
        }

        $monthStr = $currentMonth->format('Y-m');
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();
        $gridStart = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);
        $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

        $selectedBranchId = Access::admin()
            ? ($request->filled('branch_id') ? (int) $request->query('branch_id') : null)
            : (int) auth()->user()->branch_id;

        $people = User::active()->whereIn('role', ['employee', 'manager'])
            ->when(! Access::admin(), fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->when(Access::admin() && $selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->orderBy('name')->get();

        $attendanceStatus = trim((string) $request->query('attendance_status', ''));

        $shiftsQuery = Shift::with(['user.position', 'user.branch', 'branch', 'attendance', 'exceptions'])
            ->whereBetween('start_at', [$gridStart->toDateTimeString(), $gridEnd->toDateTimeString()])
            ->when(! Access::admin(), fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->when(Access::admin() && $selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->query('user_id')))
            ->when($attendanceStatus !== '', function ($q) use ($attendanceStatus) {
                if ($attendanceStatus === 'present') {
                    $q->whereHas('attendance', fn ($a) => $a->where('status', 'present'));
                } elseif ($attendanceStatus === 'late') {
                    $q->whereHas('attendance', fn ($a) => $a->where('status', 'late'));
                } elseif ($attendanceStatus === 'absent') {
                    $q->where(function ($sub) {
                        $sub->whereHas('attendance', fn ($a) => $a->where('status', 'absent'))
                            ->orWhere(function ($noAtt) {
                                $noAtt->doesntHave('attendance')
                                    ->where('end_at', '<', now('Asia/Jakarta'));
                            });
                    });
                } elseif ($attendanceStatus === 'on_duty') {
                    $q->whereHas('attendance', fn ($a) => $a->whereNotNull('checkin_at')->whereNull('checkout_at'));
                } elseif ($attendanceStatus === 'early_checkout') {
                    $q->whereHas('attendance', fn ($a) => $a->where('status', 'early_checkout'));
                } elseif ($attendanceStatus === 'pending_exception') {
                    $q->whereHas('exceptions', fn ($e) => $e->where('status', 'pending'));
                }
            })
            ->orderBy('start_at');

        $shifts = $shiftsQuery->get();
        $groupedShifts = $shifts->groupBy(fn ($s) => $s->start_at->toDateString());

        $attendanceSummaryByDate = [];
        $monthSummary = [
            'total_shifts'      => $shifts->count(),
            'total_present'     => 0,
            'total_late'        => 0,
            'total_absent'      => 0,
            'total_on_duty'     => 0,
            'total_exceptions'  => 0,
        ];

        foreach ($groupedShifts as $dStr => $dayShiftsList) {
            $presentCount = 0;
            $lateCount = 0;
            $absentCount = 0;
            $onDutyCount = 0;
            $pendingExceptionCount = 0;

            foreach ($dayShiftsList as $s) {
                $att = $s->attendance;
                if ($s->exceptions->where('status', 'pending')->isNotEmpty()) {
                    $pendingExceptionCount++;
                }

                if ($att) {
                    if ($att->status === 'present') {
                        $presentCount++;
                    } elseif ($att->status === 'late') {
                        $lateCount++;
                    } elseif ($att->status === 'absent') {
                        $absentCount++;
                    } elseif ($att->status === 'early_checkout') {
                        $lateCount++;
                    }

                    if ($att->checkin_at && ! $att->checkout_at) {
                        $onDutyCount++;
                    }
                } else {
                    if ($s->end_at && Carbon::parse($s->end_at, 'Asia/Jakarta')->isPast()) {
                        $absentCount++;
                    }
                }
            }

            $attendanceSummaryByDate[$dStr] = [
                'total_shifts'      => $dayShiftsList->count(),
                'present'           => $presentCount,
                'late'              => $lateCount,
                'absent'            => $absentCount,
                'on_duty'           => $onDutyCount,
                'pending_exception' => $pendingExceptionCount,
            ];

            $monthSummary['total_present']    += $presentCount;
            $monthSummary['total_late']       += $lateCount;
            $monthSummary['total_absent']     += $absentCount;
            $monthSummary['total_on_duty']    += $onDutyCount;
            $monthSummary['total_exceptions'] += $pendingExceptionCount;
        }

        $selectedDateParam = trim((string) $request->query('date', ''));
        $todayStr = now('Asia/Jakarta')->toDateString();
        if ($selectedDateParam !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateParam)) {
            $selectedDate = $selectedDateParam;
        } elseif ($currentMonth->isSameMonth(now('Asia/Jakarta'))) {
            $selectedDate = $todayStr;
        } else {
            $selectedDate = $startOfMonth->toDateString();
        }

        return view('attendance-review', compact(
            'exceptions', 'attempts', 'attendances', 'branches',
            'view', 'pendingExceptionsTotal',
            'currentMonth', 'monthStr', 'prevMonth', 'nextMonth',
            'gridStart', 'gridEnd', 'selectedBranchId', 'people',
            'shifts', 'groupedShifts', 'attendanceStatus',
            'attendanceSummaryByDate', 'monthSummary', 'selectedDate'
        ));
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

    public function todayPage(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->active, 403);
        $today = now('Asia/Jakarta');
        $todayDate = $today->toDateString();

        $challenge = (string) random_int(100, 999);
        session(['attendance_challenge' => $challenge]);

        if ($user->isOfficeSchedule()) {
            app(\App\Services\ShiftService::class)->ensureOfficeShiftToday($user, $today);
        }

        $shifts = Shift::with(['branch', 'attendance', 'exceptions.reviewer'])
            ->where('user_id', $user->id)
            ->whereDate('start_at', $todayDate)
            ->orderBy('start_at')
            ->get();

        $recentShifts = Shift::with(['branch', 'attendance', 'exceptions.reviewer'])
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('start_at', '>=', $today->copy()->subDays(7)->startOfDay())
            ->where('start_at', '<', $today->copy()->startOfDay())
            ->orderByDesc('start_at')
            ->get();

        $myExceptions = AttendanceException::with(['shift.branch', 'reviewer'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        $attempts = AttendanceAttempt::where('user_id', $user->id)
            ->whereDate('server_at', $todayDate)
            ->latest('id')
            ->limit(5)
            ->get();

        return view('attendance-today', compact('user', 'shifts', 'recentShifts', 'myExceptions', 'attempts', 'today'));
    }
}


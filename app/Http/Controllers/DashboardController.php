<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceException;
use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\User;
use App\Support\Access;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->active, 403);
        $today = now('Asia/Jakarta');
        $todayDate = $today->toDateString();

        $challenge = (string) random_int(100, 999);
        session(['attendance_challenge' => $challenge]);

        // Shift pribadi pengguna (untuk presensi / personal schedule)
        $shifts = Shift::with(['branch', 'attendance'])
            ->where('user_id', $user->id)
            ->whereBetween('start_at', [
                $today->copy()->subDay()->startOfDay(),
                $today->copy()->addDays(14)->endOfDay(),
            ])
            ->orderBy('start_at')
            ->get();

        // Pengajuan izin pribadi pengguna
        $leaves = LeaveRequest::where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        // Pending leaves count (untuk kompatibilitas)
        $pending = Access::manager()
            ? LeaveRequest::whereHas('user', fn ($q) => Access::admin() ? $q : $q->where('branch_id', $user->branch_id))
                ->pending()
                ->count()
            : 0;

        // Inisialisasi data berbasis peran
        $adminStats = null;
        $managerStats = null;
        $branch = null;
        $recentAttendances = collect();
        $branchTodayShifts = collect();

        if (Access::admin()) {
            // === DATA SUPER ADMIN (GLOBAL ENTERPRISE) ===
            $totalEmployees = User::where('active', true)->where('role', '!=', 'admin')->count();
            $totalBranches = Branch::count();

            // Shift seluruh perusahaan hari ini
            $companyShiftsToday = Shift::with(['user', 'branch', 'attendance'])
                ->whereDate('start_at', $todayDate)
                ->get();

            $totalShiftsToday = $companyShiftsToday->count();
            $attendedToday = $companyShiftsToday->filter(fn ($s) => $s->attendance && $s->attendance->checkin_at)->count();
            $lateToday = $companyShiftsToday->filter(fn ($s) => $s->attendance && $s->attendance->late_minutes > 0)->count();
            $absentToday = $companyShiftsToday->filter(fn ($s) => $s->attendance && $s->attendance->status === 'absent')->count();
            $notYetPresent = max(0, $totalShiftsToday - $attendedToday - $absentToday);

            $pendingLeaves = LeaveRequest::pending()->count();
            $pendingExceptions = AttendanceException::pending()->count();

            $currentMonth = $today->format('Y-m');
            $latestPayroll = PayrollRun::with('branch')
                ->where('month', $currentMonth)
                ->latest('id')
                ->first();

            $adminStats = [
                'total_employees' => $totalEmployees,
                'total_branches' => $totalBranches,
                'shifts_today' => $totalShiftsToday,
                'attended_today' => $attendedToday,
                'late_today' => $lateToday,
                'absent_today' => $absentToday,
                'not_yet_present' => $notYetPresent,
                'attendance_pct' => $totalShiftsToday > 0 ? (int) round(($attendedToday / $totalShiftsToday) * 100) : 0,
                'pending_leaves' => $pendingLeaves,
                'pending_exceptions' => $pendingExceptions,
                'total_pending_tasks' => $pendingLeaves + $pendingExceptions,
                'payroll_status' => $latestPayroll ? $latestPayroll->status : 'none',
                'payroll_month' => $currentMonth,
            ];

            // 6 Presensi terkini seluruh perusahaan
            $recentAttendances = Attendance::with(['user', 'shift.branch'])
                ->whereNotNull('checkin_at')
                ->latest('checkin_at')
                ->limit(6)
                ->get();
        } elseif (Access::manager()) {
            // === DATA MANAGER CABANG ===
            $branchId = (int) $user->branch_id;
            $branch = $branchId ? Branch::find($branchId) : null;

            $branchStaffCount = $branchId ? User::where('branch_id', $branchId)->where('active', true)->count() : 0;

            $branchTodayShifts = $branchId
                ? Shift::with(['user', 'attendance'])
                    ->where('branch_id', $branchId)
                    ->whereDate('start_at', $todayDate)
                    ->orderBy('start_at')
                    ->get()
                : collect();

            $branchShiftsCount = $branchTodayShifts->count();
            $branchAttendedCount = $branchTodayShifts->filter(fn ($s) => $s->attendance && $s->attendance->checkin_at)->count();
            $branchLateCount = $branchTodayShifts->filter(fn ($s) => $s->attendance && $s->attendance->late_minutes > 0)->count();
            $branchPendingLeaves = $branchId
                ? LeaveRequest::whereHas('user', fn ($q) => $q->where('branch_id', $branchId))->pending()->count()
                : 0;
            $branchPendingExceptions = $branchId
                ? AttendanceException::whereHas('user', fn ($q) => $q->where('branch_id', $branchId))->pending()->count()
                : 0;

            $managerStats = [
                'branch_name' => $branch?->name ?? 'Cabang Operasional',
                'branch_staff_count' => $branchStaffCount,
                'shifts_today' => $branchShiftsCount,
                'attended_today' => $branchAttendedCount,
                'late_today' => $branchLateCount,
                'attendance_pct' => $branchShiftsCount > 0 ? (int) round(($branchAttendedCount / $branchShiftsCount) * 100) : 0,
                'pending_leaves' => $branchPendingLeaves,
                'pending_exceptions' => $branchPendingExceptions,
                'total_pending_tasks' => $branchPendingLeaves + $branchPendingExceptions,
            ];
        }

        return view('dashboard', compact(
            'shifts',
            'leaves',
            'pending',
            'challenge',
            'adminStats',
            'managerStats',
            'branch',
            'recentAttendances',
            'branchTodayShifts'
        ));
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Support\Access;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->active, 403);
        $today = now('Asia/Jakarta');

        $shifts = Shift::with(['branch', 'attendance'])
            ->where('user_id', $user->id)
            ->whereBetween('start_at', [
                $today->copy()->subDay()->startOfDay(),
                $today->copy()->addDays(14)->endOfDay(),
            ])
            ->orderBy('start_at')
            ->get();

        $leaves = LeaveRequest::where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        $pending = Access::manager()
            ? LeaveRequest::whereHas('user', fn ($q) => Access::admin() ? $q : $q->where('branch_id', $user->branch_id))
                ->pending()
                ->count()
            : 0;

        $challenge = (string) random_int(100, 999);
        session(['attendance_challenge' => $challenge]);

        return view('dashboard', compact('shifts', 'leaves', 'pending', 'challenge'));
    }
}


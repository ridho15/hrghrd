<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leave\LeaveReviewRequest;
use App\Http\Requests\Leave\LeaveStoreRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveService;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = LeaveRequest::with(['user', 'days'])
            ->where(fn ($q) => Access::manager()
                ? $q->when(! Access::admin(), fn ($x) => $x->whereHas('user', fn ($u) => $u->where('branch_id', $user->branch_id)))
                : $q->where('user_id', $user->id)
            )
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                        ->orWhere('reason', 'like', "%{$search}%");
                });
            })
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id');

        $requests = $query->paginate(10)->withQueryString();

        $people = Access::manager()
            ? User::active()
                ->when(! Access::admin(), fn ($q) => $q->where('branch_id', $user->branch_id))
                ->orderBy('name')
                ->get()
            : collect();

        $days = $requests->mapWithKeys(fn ($r) => [$r->id => $r->days->sortBy('date')]);

        return view('leave', compact('requests', 'people', 'days'));
    }

    public function store(LeaveStoreRequest $request, LeaveService $service)
    {
        $service->submit($request->user(), $request->validated(), $request->file('certificate'));

        return back()->with('ok', 'Pengajuan dikirim untuk persetujuan.');
    }

    public function review(LeaveReviewRequest $request, int $id, LeaveService $service)
    {
        $data = $request->validated();
        $leave = LeaveRequest::findOrFail($id);
        $service->review($leave, auth()->user(), $data['approved_dates'] ?? [], $data['paid_dates'] ?? [], $data['review_note']);

        return back()->with('ok', 'Pengajuan diputuskan per tanggal.');
    }

    public function certificate(int $id)
    {
        $leave = LeaveRequest::findOrFail($id);
        abort_unless($leave->certificate_path, 404);
        abort_unless(Access::employee($leave->user_id), 403);

        return Storage::disk('local')->download($leave->certificate_path, $leave->certificate_name ?: 'surat-sakit');
    }
}


<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leave\LeaveReviewRequest;
use App\Http\Requests\Leave\LeaveStoreRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveService;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = LeaveRequest::with(['user.branch', 'user.position', 'days'])
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

    public function create()
    {
        $user = auth()->user();
        $people = Access::manager()
            ? User::active()
                ->when(! Access::admin(), fn ($q) => $q->where('branch_id', $user->branch_id))
                ->orderBy('name')
                ->get()
            : collect();

        return view('leave-create', compact('people'));
    }

    public function show(int $id)
    {
        $leave = LeaveRequest::with(['user.branch', 'user.position', 'days'])->findOrFail($id);
        $user = auth()->user();

        if (! Access::admin()) {
            if (Access::manager()) {
                abort_unless($leave->user?->branch_id === $user->branch_id, 403);
            } else {
                abort_unless($leave->user_id === $user->id, 403);
            }
        }

        return view('leave-show', compact('leave'));
    }

    public function store(LeaveStoreRequest $request, LeaveService $service)
    {
        $leave = $service->submit($request->user(), $request->validated(), $request->file('certificate'));

        return redirect()->route('leave.show', $leave->id)->with('ok', 'Pengajuan izin berhasil dikirim.');
    }

    public function review(LeaveReviewRequest $request, int $id, LeaveService $service)
    {
        $data = $request->validated();
        $leave = LeaveRequest::findOrFail($id);
        $service->review($leave, auth()->user(), $data['approved_dates'] ?? [], $data['paid_dates'] ?? [], $data['review_note']);

        return redirect()->route('leave.show', $leave->id)->with('ok', 'Keputusan pengajuan berhasil disimpan per tanggal.');
    }

    public function certificate(int $id)
    {
        $leave = LeaveRequest::findOrFail($id);
        abort_unless($leave->certificate_path, 404);
        abort_unless(Access::employee($leave->user_id), 403);

        return Storage::disk('local')->download($leave->certificate_path, $leave->certificate_name ?: 'surat-sakit');
    }

    public function destroy(int $id)
    {
        $leave = LeaveRequest::with('user')->findOrFail($id);
        $user = auth()->user();

        // Karyawan hanya bisa membatalkan miliknya; Manager cabangnya; Admin semua
        if (! Access::admin()) {
            if (Access::manager()) {
                abort_unless($leave->user?->branch_id === $user->branch_id, 403);
            } else {
                abort_unless($leave->user_id === $user->id, 403);
            }
        }

        if ($leave->status !== 'pending') {
            return back()->withErrors(['leave' => 'Pengajuan yang sudah diputuskan tidak dapat dibatalkan.']);
        }

        if ($leave->certificate_path && Storage::disk('local')->exists($leave->certificate_path)) {
            Storage::disk('local')->delete($leave->certificate_path);
        }

        $leave->days()->delete();
        $leave->delete();
        Audit::record('leave', $id, 'cancel', 'Pengajuan izin/sakit dibatalkan.');

        return redirect()->route('leave')->with('ok', 'Pengajuan izin berhasil dibatalkan.');
    }
}


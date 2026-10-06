<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @group Izin & Cuti
 *
 * Endpoint pengajuan izin, sakit, cuti, dan persetujuan bertingkat.
 */
class LeaveApiController extends Controller
{
    /**
     * Daftar Pengajuan Izin
     *
     * Menampilkan riwayat pengajuan izin:
     * - Employee: pengajuan pribadi
     * - Manager: pengajuan karyawan dalam cabang yang sama
     * - Admin: semua pengajuan
     *
     * Query: `type` (leave|sick), `status` (pending|approved|rejected|partial), `page`, `per_page`
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = LeaveRequest::with(['user.branch', 'user.position', 'days'])
            ->where(fn ($q) => Access::manager()
                ? $q->when(! Access::admin(), fn ($x) => $x->whereHas('user', fn ($u) => $u->where('branch_id', $user->branch_id)))
                : $q->where('user_id', $user->id)
            )
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id');

        $perPage = min((int) ($request->query('per_page', 15)), 50);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => LeaveRequestResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Detail Pengajuan Izin
     *
     * Mengambil detail satu pengajuan izin beserta rincian tanggal dan status per hari.
     */
    public function show(Request $request, int $id)
    {
        $leave = LeaveRequest::with(['user.branch', 'user.position', 'days'])->findOrFail($id);
        $user = $request->user();

        if (! Access::admin()) {
            if (Access::manager()) {
                if ($leave->user?->branch_id !== $user->branch_id) {
                    return response()->json(['success' => false, 'message' => 'Pengajuan bukan di cabang Anda.'], 403);
                }
            } else {
                if ($leave->user_id !== $user->id) {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data'    => new LeaveRequestResource($leave),
        ]);
    }

    /**
     * Ajukan Izin / Sakit
     *
     * Mengirim permohonan izin atau surat sakit baru.
     * Dapat melampirkan file surat dokter (`certificate` file: pdf/jpg/png max 2MB).
     */
    public function store(Request $request, LeaveService $service)
    {
        $data = $request->validate([
            'user_id'     => ['nullable', 'integer', 'exists:users,id'],
            'type'        => ['required', 'in:leave,sick'],
            'start_date'  => ['required', 'date', 'date_format:Y-m-d'],
            'end_date'    => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason'      => ['required', 'string', 'min:5', 'max:500'],
            'certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        $actor = $request->user();

        // Hanya manager/admin yang bisa mengajukan atas nama user lain
        if (! empty($data['user_id']) && $data['user_id'] != $actor->id && ! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Hanya manajer yang dapat mengajukan untuk karyawan lain.'], 403);
        }

        try {
            $leave = $service->submit($actor, $data, $request->file('certificate'));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Pengajuan izin ditolak.',
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin berhasil dikirim.',
            'data'    => new LeaveRequestResource($leave->load(['user.branch', 'days'])),
        ], 201);
    }

    /**
     * Review Pengajuan Izin
     *
     * Memberikan keputusan (persetujuan/penolakan) per hari terhadap pengajuan izin.
     * Hanya dapat dilakukan oleh Manager atau Admin.
     */
    public function review(Request $request, int $id, LeaveService $service)
    {
        if (! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Diperlukan role Manager.'], 403);
        }

        $data = $request->validate([
            'approved_dates'   => ['nullable', 'array'],
            'approved_dates.*' => ['date_format:Y-m-d'],
            'paid_dates'       => ['nullable', 'array'],
            'paid_dates.*'     => ['date_format:Y-m-d'],
            'review_note'      => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $leave = LeaveRequest::with('user')->findOrFail($id);

        try {
            $service->review(
                $leave,
                $request->user(),
                $data['approved_dates'] ?? [],
                $data['paid_dates'] ?? [],
                $data['review_note']
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Keputusan tidak dapat diproses.',
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Keputusan pengajuan berhasil disimpan.',
            'data'    => new LeaveRequestResource($leave->fresh()->load(['user.branch', 'days'])),
        ]);
    }

    /**
     * Batalkan Pengajuan Izin
     *
     * Membatalkan pengajuan yang masih berstatus 'pending'.
     */
    public function destroy(Request $request, int $id)
    {
        $leave = LeaveRequest::with('user')->findOrFail($id);
        $user = $request->user();

        if (! Access::admin()) {
            if (Access::manager()) {
                if ($leave->user?->branch_id !== $user->branch_id) {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
                }
            } else {
                if ($leave->user_id !== $user->id) {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
                }
            }
        }

        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan yang sudah diputuskan tidak dapat dibatalkan.',
            ], 422);
        }

        if ($leave->certificate_path && Storage::disk('local')->exists($leave->certificate_path)) {
            Storage::disk('local')->delete($leave->certificate_path);
        }

        $leave->days()->delete();
        $leave->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin berhasil dibatalkan.',
        ]);
    }
}

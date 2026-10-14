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
     * Tipe Permohonan & Kuota Cuti Karyawan
     *
     * Mengembalikan daftar jenis izin yang dapat diajukan (cuti tahunan, sakit, hari libur, pergantian cuti),
     * sisa kuota cuti tahunan, dan daftar cuti aktif yang dapat diganti (rescheduled).
     */
    public function types(Request $request)
    {
        $actor = $request->user();
        $isManager = in_array($actor->role, ['admin', 'manager'], true);

        $activeLeaves = $isManager ? LeaveRequest::where('user_id', $actor->id)
            ->whereIn('status', ['approved', 'pending'])
            ->whereIn('type', ['leave', 'leave_exchange'])
            ->latest('start_date')
            ->get(['id', 'type', 'start_date', 'end_date', 'reason', 'status']) : collect([]);

        $types = [
            [
                'key'                  => 'leave',
                'label'                => 'Cuti Tahunan / Izin Biasa',
                'description'          => 'Wajib diajukan minimal H-' . \App\Support\Rules::int('leave_notice_days') . '.',
                'requires_certificate' => false,
                'impacts_quota'        => true,
            ],
            [
                'key'                  => 'sick',
                'label'                => 'Izin Sakit',
                'description'          => 'Wajib melampirkan surat keterangan dokter resmi (PDF/JPG maks 5 MB).',
                'requires_certificate' => true,
                'impacts_quota'        => false,
            ],
        ];

        if ($isManager) {
            $types[] = [
                'key'                  => 'day_off',
                'label'                => 'Pengajuan Hari Libur (Day Off)',
                'description'          => 'Pengajuan hari libur mingguan/roster kerja.',
                'requires_certificate' => false,
                'impacts_quota'        => false,
            ];
            $types[] = [
                'key'                  => 'leave_exchange',
                'label'                => 'Pergantian Tanggal Cuti',
                'description'          => 'Menggeser/mengganti tanggal cuti aktif yang telah diajukan atau disetujui sebelumnya.',
                'requires_certificate' => false,
                'impacts_quota'        => true,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'annual_leave_quota'   => $isManager ? ($actor->annual_leave_quota ?? 12) : null,
                'used_leave_days'      => $isManager ? $actor->usedLeaveDays((int) now('Asia/Jakarta')->year) : null,
                'remaining_leave_days' => $isManager ? $actor->remainingLeaveDays() : null,
                'leave_notice_days'    => \App\Support\Rules::int('leave_notice_days'),
                'types'                => $types,
                'reschedulable_leaves' => $activeLeaves,
            ],
        ]);
    }

    /**
     * Ajukan Izin / Sakit / Hari Libur / Pergantian Cuti
     *
     * Mengirim permohonan baru.
     * Dapat melampirkan file surat dokter (`certificate` file: pdf/jpg/png max 5MB).
     */
    public function store(Request $request, LeaveService $service)
    {
        $data = $request->validate([
            'user_id'           => ['nullable', 'integer', 'exists:users,id'],
            'original_leave_id' => ['nullable', 'required_if:type,leave_exchange', 'integer', 'exists:leave_requests,id'],
            'type'              => ['required', 'in:leave,sick,day_off,leave_exchange'],
            'start_date'        => ['required', 'date', 'date_format:Y-m-d'],
            'end_date'          => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason'            => ['required', 'string', 'min:10', 'max:500'],
            'certificate'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $actor = $request->user();

        // Sentralisasi: day_off dan leave_exchange hanya boleh diajukan oleh manager/admin
        if (in_array($data['type'], ['day_off', 'leave_exchange'], true) && ! Access::manager()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengaturan hari libur dan pergantian cuti saat ini dikelola terpusat oleh Super Admin / HRD.',
            ], 403);
        }

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
            'message' => 'Pengajuan perizinan/libur berhasil dikirim.',
            'data'    => new LeaveRequestResource($leave->load(['user.branch', 'days'])),
        ], 201);
    }

    /**
     * Pengajuan Pergantian Cuti Langsung (Shortcut Reschedule)
     *
     * Mengajukan pergantian tanggal untuk cuti tertentu.
     */
    public function exchange(Request $request, int $id, LeaveService $service)
    {
        if (! Access::manager()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengaturan pergantian cuti saat ini dikelola terpusat oleh Super Admin / HRD.',
            ], 403);
        }

        $data = $request->validate([
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason'     => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $data['type'] = 'leave_exchange';
        $data['original_leave_id'] = $id;

        try {
            $leave = $service->submit($request->user(), $data);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Permohonan pergantian cuti ditolak.',
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Permohonan pergantian cuti berhasil dikirim.',
            'data'    => new LeaveRequestResource($leave->load(['user.branch', 'days'])),
        ], 201);
    }

    /**
     * Review Pengajuan Izin
     *
     * Memberikan keputusan (persetujuan/penolakan) per hari terhadap pengajuan izin.
     * Mendukung opsi `vacate_shifts` (boolean) untuk otomatis membuka jadwal terdampak menjadi slot kosong setingkat.
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
            'vacate_shifts'    => ['nullable', 'boolean'],
            'replacements'     => ['nullable', 'array'],
        ]);

        $leave = LeaveRequest::with('user')->findOrFail($id);

        try {
            $service->review(
                $leave,
                $request->user(),
                $data['approved_dates'] ?? [],
                $data['paid_dates'] ?? [],
                $data['review_note'],
                $data['replacements'] ?? [],
                (bool) ($data['vacate_shifts'] ?? false)
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
     * Unduh Lampiran Surat Keterangan
     *
     * Mengunduh berkas surat keterangan sakit yang dilampirkan pada pengajuan izin.
     * Dapat diakses oleh pemilik pengajuan sendiri, atau Manager/Admin cabangnya.
     */
    public function certificate(Request $request, int $id)
    {
        $leave = LeaveRequest::findOrFail($id);
        if (! $leave->certificate_path) {
            return response()->json(['success' => false, 'message' => 'Lampiran tidak ditemukan.'], 404);
        }
        abort_unless(Access::employee($leave->user_id), 403);

        if (! Storage::disk('local')->exists($leave->certificate_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Berkas fisik surat keterangan tidak ditemukan di server penyimpanan.',
            ], 404);
        }

        if ($request->query('inline') || $request->query('preview')) {
            return Storage::disk('local')->response($leave->certificate_path, $leave->certificate_name ?: 'surat-sakit');
        }

        return Storage::disk('local')->download($leave->certificate_path, $leave->certificate_name ?: 'surat-sakit');
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

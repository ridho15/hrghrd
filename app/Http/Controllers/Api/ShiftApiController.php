<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Services\ShiftService;
use App\Support\Access;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * @group Shift
 *
 * Endpoint manajemen jadwal shift kerja karyawan.
 */
class ShiftApiController extends Controller
{
    /**
     * Daftar Shift
     *
     * Daftar shift yang dapat diakses berdasarkan role:
     * - Employee: hanya shift milik sendiri
     * - Manager: shift karyawan di cabangnya
     * - Admin: semua shift
     *
     * Query: `date` (YYYY-MM-DD), `month` (YYYY-MM), `status` (draft|approved), `user_id`, `branch_id`, `page`, `per_page`
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Shift::with(['user', 'branch', 'attendance'])
            ->when(! Access::manager(), fn ($q) => $q->where('user_id', $user->id))
            ->when(Access::manager() && ! Access::admin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($request->query('branch_id') && Access::admin(), fn ($q) => $q->where('branch_id', $request->query('branch_id')))
            ->when($request->query('user_id') && Access::manager(), fn ($q) => $q->where('user_id', $request->query('user_id')))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('start_at', $d))
            ->when($request->query('month'), fn ($q, $m) => $q->whereRaw("DATE_FORMAT(start_at, '%Y-%m') = ?", [$m]))
            ->latest('start_at');

        $perPage = min((int) ($request->query('per_page', 15)), 50);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => ShiftResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Detail Shift
     *
     * Ambil detail lengkap satu shift termasuk data presensi aktual.
     */
    public function show(Request $request, int $id)
    {
        $shift = Shift::with(['user', 'branch', 'attendance', 'exceptions'])->findOrFail($id);
        $user = $request->user();

        // Otorisasi
        if (! Access::admin()) {
            if (Access::manager()) {
                if ($shift->branch_id !== $user->branch_id) {
                    return response()->json(['success' => false, 'message' => 'Shift bukan di cabang Anda.'], 403);
                }
            } else {
                if ($shift->user_id !== $user->id) {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data'    => new ShiftResource($shift),
        ]);
    }

    /**
     * Buat Shift Baru
     *
     * Membuat jadwal shift baru dalam status draf.
     * Hanya Manager dan Admin yang dapat membuat shift.
     *
     * Body: `user_id`, `branch_id`, `date` (YYYY-MM-DD), `start_time` (HH:MM), `end_time` (HH:MM)
     * ATAU `start_at` (ISO8601/Y-m-d H:i:s), `end_at` (ISO8601/Y-m-d H:i:s)
     */
    public function store(Request $request, ShiftService $service)
    {
        if (! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Diperlukan role Manager.'], 403);
        }

        $data = $request->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'branch_id'  => ['required', 'integer', 'exists:branches,id'],
            'date'       => ['sometimes', 'date_format:Y-m-d'],
            'start_time' => ['sometimes', 'string'],
            'end_time'   => ['sometimes', 'string'],
            'start_at'   => ['sometimes', 'date'],
            'end_at'     => ['sometimes', 'date'],
        ]);

        if (! Access::branch($data['branch_id'])) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses ke cabang ini.'], 403);
        }

        $date = $data['date'] ?? null;
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;

        if (empty($date) && ! empty($data['start_at'])) {
            $parsedStart = Carbon::parse($data['start_at'], 'Asia/Jakarta');
            $date = $parsedStart->toDateString();
            $startTime = $parsedStart->format('H:i');
        }

        if (empty($endTime) && ! empty($data['end_at'])) {
            $parsedEnd = Carbon::parse($data['end_at'], 'Asia/Jakarta');
            $endTime = $parsedEnd->format('H:i');
        }

        if (empty($date) || empty($startTime) || empty($endTime)) {
            return response()->json(['success' => false, 'message' => 'Format waktu shift tidak lengkap.'], 422);
        }

        $start = Carbon::parse($date . ' ' . $startTime, 'Asia/Jakarta');
        $end = Carbon::parse($date . ' ' . $endTime, 'Asia/Jakarta');
        if ($end->lte($start)) {
            $end->addDay();
        }

        if ($end->diffInHours($start) > 24) {
            return response()->json(['success' => false, 'message' => 'Durasi shift tidak boleh lebih dari 24 jam.'], 422);
        }

        // Cek overlap
        $conflict = Shift::where('user_id', $data['user_id'])
            ->where('start_at', '<', $end->toDateTimeString())
            ->where('end_at', '>', $start->toDateTimeString())
            ->exists();

        if ($conflict) {
            return response()->json(['success' => false, 'message' => 'Shift karyawan bertumpang tindih.'], 409);
        }

        try {
            $shift = $service->create([
                'user_id'    => $data['user_id'],
                'branch_id'  => $data['branch_id'],
                'date'       => $date,
                'start_time' => $startTime,
                'end_time'   => $endTime,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Gagal membuat shift.',
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil dibuat.',
            'data'    => new ShiftResource($shift->load('user', 'branch')),
        ], 201);
    }

    /**
     * Perbarui Shift
     *
     * Memperbarui jadwal shift yang masih dalam status draf.
     *
     * Body: `date`, `start_time`, `end_time` ATAU `start_at`, `end_at`, `reason` (opsional)
     */
    public function update(Request $request, int $id, ShiftService $service)
    {
        if (! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Diperlukan role Manager.'], 403);
        }

        $shift = Shift::findOrFail($id);

        if (! Access::branch($shift->branch_id)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak untuk cabang ini.'], 403);
        }

        if ($shift->attendance()->exists()) {
            return response()->json(['success' => false, 'message' => 'Shift dengan absensi harus dikoreksi melalui alur koreksi.'], 409);
        }

        $data = $request->validate([
            'date'             => ['sometimes', 'date_format:Y-m-d'],
            'start_time'       => ['sometimes', 'string'],
            'end_time'         => ['sometimes', 'string'],
            'start_at'         => ['sometimes', 'date'],
            'end_at'           => ['sometimes', 'date'],
            'expected_version' => ['sometimes', 'integer'],
            'reason'           => ['nullable', 'string', 'max:500'],
        ]);

        $date = $data['date'] ?? $shift->start_at->toDateString();
        $startTime = $data['start_time'] ?? $shift->start_at->format('H:i');
        $endTime = $data['end_time'] ?? $shift->end_at->format('H:i');

        if (! empty($data['start_at'])) {
            $parsedStart = Carbon::parse($data['start_at'], 'Asia/Jakarta');
            $date = $parsedStart->toDateString();
            $startTime = $parsedStart->format('H:i');
        }

        if (! empty($data['end_at'])) {
            $parsedEnd = Carbon::parse($data['end_at'], 'Asia/Jakarta');
            $endTime = $parsedEnd->format('H:i');
        }

        $start = Carbon::parse($date . ' ' . $startTime, 'Asia/Jakarta');
        $end = Carbon::parse($date . ' ' . $endTime, 'Asia/Jakarta');
        if ($end->lte($start)) {
            $end->addDay();
        }

        // Cek overlap
        $conflict = Shift::where('user_id', $shift->user_id)
            ->where('start_at', '<', $end->toDateTimeString())
            ->where('end_at', '>', $start->toDateTimeString())
            ->where('id', '!=', $shift->id)
            ->exists();

        if ($conflict) {
            return response()->json(['success' => false, 'message' => 'Shift karyawan bertumpang tindih.'], 409);
        }

        try {
            $service->update($shift, [
                'date'             => $date,
                'start_time'       => $startTime,
                'end_time'         => $endTime,
                'expected_version' => $data['expected_version'] ?? null,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Gagal memperbarui shift.',
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil diperbarui.',
            'data'    => new ShiftResource($shift->fresh()->load('user', 'branch')),
        ]);
    }

    /**
     * Setujui Shift
     *
     * Mengubah status shift dari draf menjadi disetujui (approved).
     */
    public function approve(Request $request, int $id, ShiftService $service)
    {
        if (! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Diperlukan role Manager.'], 403);
        }

        $shift = Shift::findOrFail($id);

        if (! Access::branch($shift->branch_id)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak untuk cabang ini.'], 403);
        }

        if ($shift->status === 'approved') {
            return response()->json(['success' => false, 'message' => 'Shift sudah dalam status disetujui.'], 409);
        }

        try {
            $service->approve($shift, $request->user());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Gagal menyetujui shift.',
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil disetujui.',
            'data'    => new ShiftResource($shift->fresh()->load('user', 'branch')),
        ]);
    }

    /**
     * Hapus / Batalkan Shift
     *
     * Menghapus jadwal shift yang belum memiliki absensi.
     */
    public function destroy(Request $request, int $id)
    {
        if (! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Diperlukan role Manager.'], 403);
        }

        $shift = Shift::with(['attendance', 'user'])->findOrFail($id);

        if (! Access::admin() && $shift->branch_id !== $request->user()->branch_id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak untuk cabang ini.'], 403);
        }

        if ($shift->attendance()->exists()) {
            return response()->json(['success' => false, 'message' => 'Shift yang sudah memiliki absensi tidak dapat dihapus.'], 409);
        }

        $date = $shift->start_at->toDateString();
        $userName = $shift->user?->name ?? 'Karyawan';
        $shift->delete();
        Audit::record('shift', $id, 'delete', "Jadwal shift {$userName} pada {$date} dibatalkan dan dihapus.");

        return response()->json([
            'success' => true,
            'message' => "Jadwal shift untuk {$userName} berhasil dibatalkan.",
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShiftDirectReassignRequest;
use App\Http\Requests\Admin\ShiftExchangePeerRequest;
use App\Http\Requests\Admin\ShiftExchangeReviewRequest;
use App\Http\Requests\Admin\ShiftExchangeStoreRequest;
use App\Http\Requests\Api\ShiftExchangeOpenSlotRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Models\ShiftExchange;
use App\Models\User;
use App\Services\ShiftExchangeService;
use App\Services\ShiftService;
use App\Support\Access;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'is_open_slot'=> ['sometimes', 'boolean'],
            'position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'user_id'     => ['required_unless:is_open_slot,true,1', 'nullable', 'integer', 'exists:users,id'],
            'branch_id'   => ['required', 'integer', 'exists:branches,id'],
            'date'        => ['sometimes', 'date_format:Y-m-d'],
            'start_time'  => ['sometimes', 'string'],
            'end_time'    => ['sometimes', 'string'],
            'start_at'    => ['sometimes', 'date'],
            'end_at'      => ['sometimes', 'date'],
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

        // Cek overlap jika ada user_id
        if (! empty($data['user_id'])) {
            $conflict = Shift::where('user_id', $data['user_id'])
                ->where('start_at', '<', $end->toDateTimeString())
                ->where('end_at', '>', $start->toDateTimeString())
                ->exists();

            if ($conflict) {
                return response()->json(['success' => false, 'message' => 'Shift karyawan bertumpang tindih.'], 409);
            }
        }

        try {
            $shift = $service->create([
                'user_id'     => $data['user_id'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'branch_id'   => $data['branch_id'],
                'date'        => $date,
                'start_time'  => $startTime,
                'end_time'    => $endTime,
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
            'data'    => new ShiftResource($shift->load('user', 'branch', 'position')),
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

        $shift = Shift::with(['attendance', 'exceptions', 'user'])->findOrFail($id);

        if (! Access::admin() && $shift->branch_id !== $request->user()->branch_id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak untuk cabang ini.'], 403);
        }

        if (! Access::admin()) {
            if ($shift->attendance()->exists() || $shift->exceptions()->exists()) {
                return response()->json(['success' => false, 'message' => 'Shift yang sudah memiliki rekaman absensi atau tiket kendala presensi tidak dapat dihapus.'], 409);
            }
        }

        $date = $shift->start_at->toDateString();
        $userName = $shift->user?->name ?? 'Karyawan';

        DB::transaction(function () use ($shift, $id, $userName, $date) {
            if (Access::admin()) {
                if (\Illuminate\Support\Facades\Schema::hasTable('shift_exchanges')) {
                    ShiftExchange::where('requester_shift_id', $id)->orWhere('target_shift_id', $id)->delete();
                }
                $shift->exceptions()->delete();
                $shift->attendance()?->delete();
            }

            $shift->delete();
            $action = Access::admin() ? 'force_delete' : 'delete';
            $auditMsg = Access::admin()
                ? "Super Admin " . auth()->user()->name . " menghapus paksa shift ID {$id} milik {$userName} pada {$date} beserta seluruh data relasi absensi."
                : "Jadwal shift {$userName} pada {$date} dibatalkan dan dihapus.";
            Audit::record('shift', $id, $action, $auditMsg);
        });

        return response()->json([
            'success' => true,
            'message' => "Jadwal shift untuk {$userName} berhasil dibatalkan.",
        ]);
    }

    /**
     * Kalender Jadwal Shift
     */
    public function calendar(Request $request)
    {
        $user = $request->user();
        $monthParam = trim((string) $request->query('month', ''));
        try {
            $currentMonth = $monthParam !== '' ? Carbon::createFromFormat('!Y-m', $monthParam, 'Asia/Jakarta') : now('Asia/Jakarta');
        } catch (\Throwable) {
            $currentMonth = now('Asia/Jakarta');
        }

        $gridStart = $currentMonth->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $currentMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $selectedBranchId = Access::admin()
            ? ($request->filled('branch_id') ? (int) $request->query('branch_id') : null)
            : (int) $user->branch_id;

        $shifts = Shift::with(['user', 'branch', 'attendance'])
            ->whereBetween('start_at', [$gridStart->toDateTimeString(), $gridEnd->toDateTimeString()])
            ->when(! Access::admin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when(Access::admin() && $selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->query('user_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('start_at')
            ->get();

        return response()->json([
            'success' => true,
            'month'   => $currentMonth->format('Y-m'),
            'data'    => ShiftResource::collection($shifts),
        ]);
    }

    /**
     * Ambil Daftar Rekan Kerja Se-Cabang beserta Jadwal Shift Mendatang
     *
     * Endpoint untuk karyawan mobile memilih rekan kerja dan jadwal yang ingin ditukar.
     */
    public function colleagues(Request $request)
    {
        $user = $request->user();
        if (! $user->branch_id) {
            return response()->json([
                'success' => true,
                'data'    => [],
            ]);
        }

        $now = now('Asia/Jakarta');
        $end = $now->copy()->addDays(35)->endOfDay();

        $colleagues = User::with(['position'])
            ->where('branch_id', $user->branch_id)
            ->where('id', '!=', $user->id)
            ->where('active', true)
            ->whereIn('role', ['employee', 'manager'])
            ->with(['shifts' => function ($q) use ($now, $end) {
                $q->whereBetween('start_at', [$now->toDateTimeString(), $end->toDateTimeString()])
                    ->where('status', 'approved')
                    ->whereDoesntHave('attendance')
                    ->whereDoesntHave('exceptions')
                    ->whereDoesntHave('exchanges', function ($sq) {
                        $sq->whereIn('status', ['pending_peer', 'pending_manager']);
                    })
                    ->orderBy('start_at');
            }])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar rekan kerja berhasil dimuat.',
            'data'    => $colleagues->map(function ($c) {
                return [
                    'id'            => $c->id,
                    'name'          => $c->name,
                    'email'         => $c->email,
                    'position_id'   => $c->position_id,
                    'position_name' => $c->position?->name,
                    'shifts'        => $c->shifts->map(function ($s) {
                        return [
                            'id'             => $s->id,
                            'date'           => $s->start_at->toDateString(),
                            'start_at'       => $s->start_at->toIso8601String(),
                            'end_at'         => $s->end_at->toIso8601String(),
                            'start_time'     => $s->start_at->format('H:i'),
                            'end_time'       => $s->end_at->format('H:i'),
                            'duration_hours' => round($s->end_at->diffInMinutes($s->start_at) / 60, 1),
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * Ambil Daftar Jadwal Kosong (Open Slots) untuk Level Setingkat
     *
     * Mengambil daftar jadwal kosong pada cabang pengguna yang sesuai dengan level/jabatannya.
     */
    public function openSlots(Request $request, ShiftExchangeService $service)
    {
        $user = $request->user();
        $startDate = $request->filled('start_date') ? Carbon::parse($request->query('start_date'), 'Asia/Jakarta') : null;
        $endDate = $request->filled('end_date') ? Carbon::parse($request->query('end_date'), 'Asia/Jakarta') : null;

        $slots = $service->getAvailableOpenSlots($user, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Daftar jadwal kosong level setingkat berhasil dimuat.',
            'data'    => $slots->map(function ($s) {
                return [
                    'id'             => $s->id,
                    'branch_id'      => $s->branch_id,
                    'branch_name'    => $s->branch?->name,
                    'position_id'    => $s->position_id,
                    'position_name'  => $s->position_name,
                    'date'           => $s->start_at->toDateString(),
                    'start_at'       => $s->start_at->toIso8601String(),
                    'end_at'         => $s->end_at->toIso8601String(),
                    'start_time'     => $s->start_at->format('H:i'),
                    'end_time'       => $s->end_at->format('H:i'),
                    'duration_hours' => round($s->end_at->diffInMinutes($s->start_at) / 60, 1),
                    'is_open_slot'   => true,
                ];
            }),
        ]);
    }

    /**
     * Ajukan Ganti Shift atau Ganti Libur ke Jadwal Kosong
     *
     * Endpoint untuk karyawan mobile mengajukan permohonan ke jadwal kosong level setingkat.
     */
    public function exchangeOpenSlot(ShiftExchangeOpenSlotRequest $request, ShiftExchangeService $service)
    {
        if (! Access::manager()) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur pengajuan tukar shift mandiri dinonaktifkan sementara. Pengaturan jadwal shift dan libur dikelola terpusat oleh Super Admin / HRD.',
            ], 403);
        }

        $data = $request->validated();
        $targetShift = Shift::with(['branch', 'position'])->findOrFail((int) $data['target_shift_id']);
        $reqShift = ! empty($data['requester_shift_id']) ? Shift::findOrFail((int) $data['requester_shift_id']) : null;

        $exchange = $service->requestOpenSlotExchange(
            $request->user(),
            $data['type'],
            $targetShift,
            $reqShift,
            $data['reason'],
            $data['off_date'] ?? null,
            ! empty($data['user_id']) ? (int) $data['user_id'] : null
        );

        $msg = ($data['type'] === 'change_shift' ? 'Permohonan ganti shift' : 'Permohonan ganti libur') . ' ke jadwal kosong berhasil diajukan dan menunggu persetujuan Super Admin / HRD.';

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data'    => $exchange->load([
                'requester.position', 'targetShift.branch', 'targetShift.position', 'requesterShift', 'branch'
            ]),
        ], 201);
    }

    /**
     * Daftar Permohonan Pergantian Shift
     */
    public function exchanges(Request $request, ShiftExchangeService $service)
    {
        $service->expireOutdatedPeerRequests();

        $user = $request->user();
        $query = ShiftExchange::with([
            'requester.position', 'replacement.position',
            'requesterShift.branch', 'targetShift.branch', 'targetShift.position',
            'branch', 'reviewer'
        ])
        ->when(! Access::manager(), fn ($q) => $q->forUser($user->id))
        ->when(Access::manager() && ! Access::admin(), fn ($q) => $q->where('branch_id', $user->branch_id))
        ->when(Access::admin() && $request->filled('branch_id'), fn ($q) => $q->where('branch_id', (int) $request->query('branch_id')))
        ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
        ->latest('id');

        $perPage = min((int) ($request->query('per_page', 15)), 50);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Buat Permohonan Pergantian / Pertukaran Shift
     */
    public function exchangeStore(ShiftExchangeStoreRequest $request, int $id, ShiftExchangeService $service)
    {
        if (! Access::manager()) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur pengajuan tukar shift mandiri dinonaktifkan sementara. Pengaturan jadwal shift dan libur dikelola terpusat oleh Super Admin / HRD.',
            ], 403);
        }

        $shift = Shift::findOrFail($id);
        $data = $request->validated();

        if ($data['type'] === 'replacement') {
            $replacement = User::findOrFail((int) $data['replacement_id']);
            $exchange = $service->requestReplacement($request->user(), $shift, $replacement, $data['reason']);
            $msg = 'Permohonan penggantian shift berhasil diajukan.';
        } elseif ($data['type'] === 'swap') {
            $targetShift = Shift::with('user')->findOrFail((int) $data['target_shift_id']);
            $exchange = $service->requestSwap($request->user(), $shift, $targetShift, $data['reason']);
            $msg = 'Permohonan pertukaran jadwal berhasil diajukan.';
        } else {
            $targetShift = Shift::with(['position', 'branch'])->findOrFail((int) $data['target_shift_id']);
            $exchange = $service->requestOpenSlotExchange(
                $request->user(),
                $data['type'],
                $targetShift,
                $shift,
                $data['reason'],
                $data['target_date'] ?? null
            );
            $msg = ($data['type'] === 'change_shift' ? 'Permohonan ganti shift' : 'Permohonan ganti libur') . ' ke jadwal kosong berhasil diajukan.';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data'    => $exchange->load(['requester.position', 'replacement.position', 'requesterShift', 'targetShift.position', 'targetShift.branch']),
        ], 201);
    }

    /**
     * Respon Rekan Pengganti (Terima/Tolak)
     */
    public function exchangePeerRespond(ShiftExchangePeerRequest $request, int $id, ShiftExchangeService $service)
    {
        $exchange = ShiftExchange::findOrFail($id);
        $data = $request->validated();
        $isAccept = $data['action'] === 'accept';

        $exchange = $service->peerRespond($exchange, $request->user(), $isAccept, $data['note'] ?? null);

        return response()->json([
            'success' => true,
            'message' => $isAccept ? 'Permohonan pergantian shift disetujui.' : 'Permohonan pergantian shift ditolak.',
            'data'    => $exchange->fresh()->load(['requester', 'replacement', 'requesterShift', 'targetShift']),
        ]);
    }

    /**
     * Tinjau Manajerial / Admin (Setujui/Tolak)
     */
    public function exchangeReview(ShiftExchangeReviewRequest $request, int $id, ShiftExchangeService $service)
    {
        $exchange = ShiftExchange::findOrFail($id);
        $data = $request->validated();
        $isApprove = $data['decision'] === 'approve';

        $exchange = $service->managerReview($exchange, $request->user(), $isApprove, $data['note'] ?? null);

        return response()->json([
            'success' => true,
            'message' => $isApprove ? 'Permohonan pergantian shift berhasil disetujui manajerial.' : 'Permohonan pergantian shift ditolak.',
            'data'    => $exchange->fresh()->load(['requester', 'replacement', 'requesterShift', 'targetShift']),
        ]);
    }

    /**
     * Batalkan Permohonan oleh Pemohon Mandiri (Self-Cancel)
     */
    public function exchangeCancel(Request $request, int $id, ShiftExchangeService $service)
    {
        $exchange = ShiftExchange::findOrFail($id);
        $reason = $request->input('reason');

        $exchange = $service->cancelByRequester($exchange, $request->user(), $reason);

        return response()->json([
            'success' => true,
            'message' => 'Permohonan pertukaran shift berhasil dibatalkan.',
            'data'    => $exchange->fresh()->load(['requester', 'replacement', 'requesterShift', 'targetShift']),
        ]);
    }

    /**
     * Penugasan Staf Pengganti Langsung oleh Manajer
     */
    public function directReassign(ShiftDirectReassignRequest $request, int $id, ShiftExchangeService $service)
    {
        $shift = Shift::findOrFail($id);
        $data = $request->validated();
        $replacement = User::findOrFail((int) $data['replacement_id']);

        $exchange = $service->directReassign($shift, $replacement, $request->user(), $data['reason']);

        return response()->json([
            'success' => true,
            'message' => "Shift berhasil dialihkan langsung ke {$replacement->name}.",
            'data'    => $exchange->fresh()->load(['requester', 'replacement', 'requesterShift']),
        ]);
    }
}

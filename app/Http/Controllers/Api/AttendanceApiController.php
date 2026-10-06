<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\AttendanceException;
use App\Models\Branch;
use App\Models\Shift;
use App\Services\AttendanceService;
use App\Support\Access;
use App\Support\Period;
use Illuminate\Http\Request;

/**
 * @group Presensi
 *
 * Endpoint check-in, check-out, riwayat presensi, QR code, dan pengecualian presensi.
 */
class AttendanceApiController extends Controller
{
    /**
     * Check-In Presensi
     *
     * Lakukan check-in ke shift yang telah disetujui menggunakan kode QR cabang.
     * Memerlukan validasi geofence GPS dan device token.
     *
     * Body: `shift_id`, `qr_code`, `latitude`, `longitude`, `accuracy`, `device_token` (atau `device_hash`), `challenge` (opsional)
     */
    public function checkIn(Request $request, AttendanceService $service)
    {
        $data = $request->validate([
            'shift_id'     => ['required', 'integer', 'exists:shifts,id'],
            'qr_code'      => ['required', 'string'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy'     => ['nullable', 'numeric', 'min:0'],
            'device_token' => ['nullable', 'string'],
            'device_hash'  => ['nullable', 'string'],
            'challenge'    => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Siapkan tantangan presensi anti-replay
        $challenge = (string) ($data['challenge'] ?? session('attendance_challenge') ?? random_int(100, 999));
        session(['attendance_challenge' => $challenge]);

        // Siapkan device token
        $deviceToken = (string) (
            $data['device_token']
            ?? $data['device_hash']
            ?? $request->header('X-Device-Token')
            ?? $request->header('X-Device-Id')
            ?? ($user->device_hash ? '' : 'mobile_app_' . $user->id)
        );

        $actInput = [
            'qr_code'   => strtoupper(trim($data['qr_code'])),
            'latitude'  => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy'  => $data['accuracy'] ?? null,
            'challenge' => $challenge,
        ];

        try {
            $service->act($user, $data['shift_id'], 'in', $actInput, $deviceToken);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Aksi presensi ditolak.',
            ], $e->getStatusCode());
        }

        $attendance = Attendance::with(['shift.branch'])
            ->where('shift_id', $data['shift_id'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Check-in berhasil tercatat.',
            'data'    => $attendance ? new AttendanceResource($attendance) : null,
        ], 201);
    }

    /**
     * Check-Out Presensi
     *
     * Lakukan check-out dari shift aktif. Menghitung keterlambatan dan durasi lembur otomatis.
     *
     * Body: `shift_id`, `latitude`, `longitude`, `accuracy`, `device_token`, `challenge`
     */
    public function checkOut(Request $request, AttendanceService $service)
    {
        $data = $request->validate([
            'shift_id'     => ['required', 'integer', 'exists:shifts,id'],
            'qr_code'      => ['nullable', 'string'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy'     => ['nullable', 'numeric', 'min:0'],
            'device_token' => ['nullable', 'string'],
            'device_hash'  => ['nullable', 'string'],
            'challenge'    => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $challenge = (string) ($data['challenge'] ?? session('attendance_challenge') ?? random_int(100, 999));
        session(['attendance_challenge' => $challenge]);

        $deviceToken = (string) (
            $data['device_token']
            ?? $data['device_hash']
            ?? $request->header('X-Device-Token')
            ?? $request->header('X-Device-Id')
            ?? ($user->device_hash ? '' : 'mobile_app_' . $user->id)
        );

        $shift = Shift::find($data['shift_id']);
        $branch = $shift ? Branch::find($shift->branch_id) : null;
        $qrCode = $data['qr_code'] ?? ($branch ? AttendanceService::qr($branch) : '');

        $actInput = [
            'qr_code'   => strtoupper(trim($qrCode)),
            'latitude'  => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy'  => $data['accuracy'] ?? null,
            'challenge' => $challenge,
        ];

        try {
            $service->act($user, $data['shift_id'], 'out', $actInput, $deviceToken);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Aksi presensi ditolak.',
            ], $e->getStatusCode());
        }

        $attendance = Attendance::with(['shift.branch'])
            ->where('shift_id', $data['shift_id'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Check-out berhasil tercatat.',
            'data'    => $attendance ? new AttendanceResource($attendance) : null,
        ]);
    }

    /**
     * Riwayat Presensi
     *
     * Daftar presensi terpaginasi (milik pribadi untuk karyawan, atau cabang untuk manager).
     *
     * Query: `month` (YYYY-MM), `user_id`, `page`, `per_page`
     */
    public function history(Request $request)
    {
        $user = $request->user();
        $perPage = min((int) ($request->query('per_page', 15)), 50);

        $query = Attendance::with(['shift.branch', 'user'])
            ->when(! Access::manager(), fn ($q) => $q->where('user_id', $user->id))
            ->when(Access::manager() && ! Access::admin(), fn ($q) => $q->whereHas(
                'user', fn ($u) => $u->where('branch_id', $user->branch_id)
            ))
            ->when($request->query('month'), fn ($q, $month) => $q->whereHas(
                'shift', fn ($s) => $s->whereRaw("DATE_FORMAT(start_at, '%Y-%m') = ?", [$month])
            ))
            ->when($request->query('user_id') && Access::manager(), fn ($q) => $q->where('user_id', $request->query('user_id')))
            ->latest('id');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => AttendanceResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Generate QR Code Cabang
     *
     * Menghasilkan kode QR cabang yang berlaku selama 30 detik.
     * Hanya dapat diakses oleh Manager dan Admin.
     *
     * Query: `branch_id` (opsional untuk manager, default cabangnya)
     */
    public function qrCode(Request $request)
    {
        if (! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Diperlukan role Manager.'], 403);
        }

        $branchId = (int) ($request->query('branch_id') ?: $request->user()->branch_id);

        if (! Access::branch($branchId)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses ke cabang ini.'], 403);
        }

        $branch = Branch::findOrFail($branchId);
        $slot = intdiv(now()->timestamp, 30);

        return response()->json([
            'success' => true,
            'data'    => [
                'code'       => AttendanceService::qr($branch, $slot),
                'expires_at' => ($slot + 1) * 30,
                'expires_in' => ($slot + 1) * 30 - now()->timestamp,
                'branch'     => [
                    'id'   => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ],
            ],
        ]);
    }

    /**
     * Ajukan Pengecualian Presensi
     *
     * Mengajukan form kendala/pengecualian jika presensi check-in/check-out terkendala teknis.
     *
     * Body: `action` (in|out), `reason` (min:10 karakter)
     */
    public function exception(Request $request, int $shiftId)
    {
        $data = $request->validate([
            'action' => ['required', 'in:in,out'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $shift = Shift::where('id', $shiftId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $shift || $shift->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Shift tidak ditemukan atau belum disetujui.',
            ], 403);
        }

        Period::writable($shift->branch_id, $shift->start_at->toDateString());

        $duplicate = AttendanceException::where('shift_id', $shiftId)
            ->where('action', $data['action'])
            ->pending()
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Pengecualian untuk tindakan ini sudah menunggu keputusan.',
            ], 409);
        }

        $exception = AttendanceException::create([
            'user_id'  => $request->user()->id,
            'shift_id' => $shiftId,
            'action'   => $data['action'],
            'reason'   => $data['reason'],
            'status'   => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengecualian berhasil diajukan. Menunggu keputusan Manager.',
            'data'    => [
                'exception_id' => $exception->id,
                'action'       => $exception->action,
                'status'       => $exception->status,
            ],
        ], 201);
    }
}

<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceAttempt;
use App\Models\AttendanceException;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Period;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceService
{
    public static function qr(object $branch, ?int $slot = null): string
    {
        $slot ??= intdiv(now()->timestamp, 30);

        return strtoupper(substr(hash_hmac('sha256', $branch->id . '|' . $slot, $branch->qr_secret), 0, 8));
    }

    // $requireChallenge hanya valid untuk pemanggil berbasis sesi browser (AttendanceController
    // web), di mana `attendance_challenge` ditaruh di sesi saat halaman dimuat (DashboardController)
    // dan dicocokkan kembali saat form disubmit lewat sesi cookie yang sama. Klien mobile berbasis
    // Bearer token (Sanctum) tidak membawa cookie sesi antar-request, sehingga tidak pernah ada
    // kontinuitas sesi untuk dicocokkan; AttendanceApiController memanggil dengan false.
    public function act(object $user, int $shiftId, string $action, array $input, string $deviceToken, bool $requireChallenge = true): void
    {
        abort_unless(in_array($action, ['in', 'out'], true), 404);

        try {
            $lateError = DB::transaction(function () use ($user, $shiftId, $action, $input, $deviceToken, $requireChallenge) {
                $shift = Shift::where('id', $shiftId)->where('user_id', $user->id)->first();
                $now = now('Asia/Jakarta');

                if (! $shift || $shift->status !== 'approved') {
                    $this->reject($user->id, $shiftId, $action, 'Shift tidak disetujui', [], $now);
                }

                Period::writable($shift->branch_id, $shift->start_at->toDateString());
                $branch = Branch::find($shift->branch_id);
                $start = Carbon::parse($shift->start_at, 'Asia/Jakarta');
                $end = Carbon::parse($shift->end_at, 'Asia/Jakarta');
                $attendance = Attendance::where('shift_id', $shiftId)->first();

                $evidence = [
                    'server_at' => $now->toIso8601String(),
                    'branch_id' => $shift->branch_id,
                    'latitude' => isset($input['latitude']) ? round((float) $input['latitude'], 5) : null,
                    'longitude' => isset($input['longitude']) ? round((float) $input['longitude'], 5) : null,
                    'accuracy_m' => isset($input['accuracy']) ? round((float) $input['accuracy']) : null,
                    'device_hash' => hash('sha256', $deviceToken),
                    'ip' => request()->ip(),
                ];

                if ($action === 'in') {
                    if ($attendance) {
                        $this->reject($user->id, $shiftId, $action, 'Check-in sudah tercatat', $evidence, $now);
                    }
                    if ($now->lt($start->copy()->subMinutes(Rules::int('checkin_early_minutes')))) {
                        $this->reject($user->id, $shiftId, $action, 'Terlalu awal', $evidence, $now);
                    }

                    $late = max(0, (int) $start->diffInMinutes($now, false));
                    if ($late > Rules::int('late_reject_minutes')) {
                        $absent = Attendance::create([
                            'shift_id' => $shiftId,
                            'user_id' => $user->id,
                            'checkin_at' => $now,
                            'status' => 'absent',
                            'late_minutes' => $late,
                            'checkin_evidence' => $evidence,
                        ]);

                        $this->attempt($user->id, $shiftId, $action, 'rejected', 'Lebih dari batas terlambat', $evidence, $now);
                        Audit::record('attendance', $absent->id, 'auto_absent', 'Terlambat ' . $late . ' menit');

                        return 'Terlambat lebih dari batas; ditandai alfa. Ajukan pengecualian bila ada kendala.';
                    }

                    if ($now->gt($end)) {
                        $this->reject($user->id, $shiftId, $action, 'Shift telah selesai', $evidence, $now);
                    }
                } else {
                    if (! $attendance || ! $attendance->checkin_at || $attendance->checkout_at) {
                        $this->reject($user->id, $shiftId, $action, 'Check-out tidak sah atau ganda', $evidence, $now);
                    }
                    if ($now->lt(Carbon::parse($attendance->checkin_at, 'Asia/Jakarta')) ||
                        $now->gt($end->copy()->addHours(Rules::int('checkout_late_hours')))) {
                        $this->reject($user->id, $shiftId, $action, 'Di luar jendela check-out', $evidence, $now);
                    }
                }

                if (! $deviceToken || ($user->device_hash && ! hash_equals($user->device_hash, $evidence['device_hash']))) {
                    $this->reject($user->id, $shiftId, $action, 'Perangkat berbeda', $evidence, $now);
                }

                if ($requireChallenge) {
                    if (($input['challenge'] ?? '') !== session('attendance_challenge')) {
                        $this->reject($user->id, $shiftId, $action, 'Tantangan tidak cocok', $evidence, $now);
                    }
                    session()->forget('attendance_challenge');
                }

                $slot = intdiv(now()->timestamp, 30);
                $qrValid = hash_equals(self::qr($branch, $slot), (string) ($input['qr_code'] ?? ''))
                    || hash_equals(self::qr($branch, $slot - 1), (string) ($input['qr_code'] ?? ''));

                if (! $qrValid) {
                    $this->reject($user->id, $shiftId, $action, 'Kode cabang kedaluwarsa atau salah', $evidence, $now);
                }

                if ($branch->latitude === null || $branch->longitude === null || ! isset($input['latitude'], $input['longitude'], $input['accuracy'])) {
                    $this->reject($user->id, $shiftId, $action, 'Lokasi cabang atau GPS tidak tersedia', $evidence, $now);
                }

                // Android melaporkan lokasi yang berasal dari aplikasi fake-GPS/mock
                // location provider lewat flag ini (dikirim klien dari Position.isMocked
                // milik package geolocator). Koordinat yang cocok dengan geofence tidak
                // berarti apa-apa kalau sumbernya memang palsu, jadi ditolak langsung
                // di sini sebelum pengecekan jarak.
                $evidence['is_mocked'] = (bool) ($input['is_mocked'] ?? false);
                if ($evidence['is_mocked']) {
                    $this->reject($user->id, $shiftId, $action, 'Lokasi GPS terdeteksi palsu (mock location). Matikan aplikasi fake-GPS sebelum presensi', $evidence, $now);
                }

                $distance = self::distance((float) $branch->latitude, (float) $branch->longitude, (float) $input['latitude'], (float) $input['longitude']);
                $evidence['distance_m'] = round($distance);

                if ((float) $input['accuracy'] > 100 || $distance > $branch->radius_m) {
                    $this->reject($user->id, $shiftId, $action, 'Lokasi di luar area atau akurasi GPS rendah', $evidence, $now);
                }

                if (! $user->device_hash) {
                    User::where('id', $user->id)->update(['device_hash' => $evidence['device_hash']]);
                }

                if ($action === 'in') {
                    $late = max(0, (int) $start->diffInMinutes($now, false));
                    $units = Rules::lateUnits($late);
                    $prior = AttendanceAttempt::where('user_id', $user->id)->where('result', 'accepted')
                        ->where('server_at', '>=', $now->copy()->subHour())->where('shift_id', '!=', $shiftId)->exists();

                    $attendance = Attendance::create([
                        'shift_id' => $shiftId,
                        'user_id' => $user->id,
                        'checkin_at' => $now,
                        'status' => $units ? 'late' : 'present',
                        'late_minutes' => $late,
                        'late_units' => $units,
                        'checkin_evidence' => $evidence,
                        'flags' => $prior ? ['Beberapa absensi dalam satu jam'] : [],
                    ]);
                } else {
                    $durationMinutes = (int) Carbon::parse($attendance->checkin_at, 'Asia/Jakarta')->diffInMinutes($now);
                    $flags = $attendance->flags ?? [];
                    $status = $attendance->status;

                    if ($durationMinutes < Rules::int('min_work_duration_minutes')) {
                        $flags[] = 'Durasi kerja sangat singkat (< ' . Rules::int('min_work_duration_minutes') . ' menit): ' . $durationMinutes . ' menit';
                        $status = 'early_checkout';
                    }

                    $extra = max(0, (int) $end->diffInMinutes($now, false));
                    $rawOvertime = max(0, $extra - Rules::int('overtime_threshold_minutes'));
                    $maxDailyOvertime = Rules::int('overtime_max_daily_minutes');
                    $overtime = min($maxDailyOvertime, $rawOvertime);
                    if ($rawOvertime > $maxDailyOvertime) {
                        $flags[] = 'Lembur melebihi batas legal harian (' . $maxDailyOvertime . ' menit / 4 jam), dibatasi ke ' . $maxDailyOvertime . ' menit';
                    }

                    $attendance->update([
                        'checkout_at' => $now,
                        'checkout_evidence' => $evidence,
                        'status' => $status,
                        'overtime_minutes' => $overtime,
                        'flags' => $flags,
                    ]);
                }

                $this->attempt($user->id, $shiftId, $action, 'accepted', null, $evidence, $now);
                Audit::record('attendance', $attendance->id, $action === 'in' ? 'checkin' : 'checkout');

                return null;
            });
        } catch (ValidationException $e) {
            $shift = Shift::find($shiftId);
            $branch = $shift ? Branch::find($shift->branch_id) : null;
            $distance = $branch && isset($input['latitude'], $input['longitude'])
                ? round(self::distance((float) $branch->latitude, (float) $branch->longitude, (float) $input['latitude'], (float) $input['longitude'])) : null;

            $this->attempt($user->id, $shiftId, $action, 'rejected', $e->errors()['attendance'][0] ?? 'Ditolak', [
                'latitude' => $input['latitude'] ?? null,
                'longitude' => $input['longitude'] ?? null,
                'accuracy_m' => $input['accuracy'] ?? null,
                'distance_m' => $distance,
                'qr_valid' => $branch ? (
                    hash_equals(self::qr($branch, intdiv(now()->timestamp, 30)), (string) ($input['qr_code'] ?? '')) ||
                    hash_equals(self::qr($branch, intdiv(now()->timestamp, 30) - 1), (string) ($input['qr_code'] ?? ''))
                ) : false,
                'device_matches' => ! $user->device_hash || hash_equals($user->device_hash, hash('sha256', $deviceToken)),
                'ip' => request()->ip(),
            ], now('Asia/Jakarta'));

            throw $e;
        }

        if ($lateError) {
            throw ValidationException::withMessages(['attendance' => $lateError]);
        }
    }

    public function reviewException(AttendanceException $ex, User $reviewer, string $decision, string $reviewNote, ?string $actualTime = null): void
    {
        abort_unless($ex->status === 'pending', 409);
        $shift = $ex->shift;
        abort_unless(Access::branch($shift->branch_id), 403);
        abort_if($ex->user_id === $reviewer->id && ! Access::admin(), 403, 'Manajer tidak dapat menyetujui pengajuan pengecualian sendiri.');
        Period::writable($shift->branch_id, $shift->start_at->toDateString());

        DB::transaction(function () use ($ex, $shift, $reviewer, $decision, $reviewNote, $actualTime) {
            if ($decision === 'approved') {
                $attendance = Attendance::where('shift_id', $shift->id)->first();
                $shiftDate = Carbon::parse($shift->start_at, 'Asia/Jakarta')->toDateString();

                $timeToUse = $actualTime ?: $ex->claimed_time;
                if ($timeToUse) {
                    $claimed = Carbon::parse($shiftDate . ' ' . $timeToUse, 'Asia/Jakarta');
                } else {
                    $claimed = $ex->action === 'in'
                        ? Carbon::parse($shift->start_at, 'Asia/Jakarta')
                        : Carbon::parse($shift->end_at, 'Asia/Jakarta');
                }

                if ($ex->action === 'in') {
                    abort_if($attendance && $attendance->checkin_at, 409, 'Sudah check-in.');
                    $late = max(0, (int) Carbon::parse($shift->start_at, 'Asia/Jakarta')->diffInMinutes($claimed, false));
                    $values = [
                        'checkin_at' => $claimed,
                        'status' => 'corrected',
                        'late_minutes' => $late,
                        'late_units' => Rules::lateUnits($late),
                        'checkin_evidence' => ['exception_id' => $ex->id, 'approved_by' => $reviewer->id],
                    ];

                    if ($attendance) {
                        $attendance->update($values);
                    } else {
                        Attendance::create($values + ['shift_id' => $shift->id, 'user_id' => $ex->user_id]);
                    }
                } else {
                    abort_unless($attendance && $attendance->checkin_at && ! $attendance->checkout_at, 409, 'Check-in belum ada atau sudah check-out.');
                    abort_if($claimed->lt(Carbon::parse($attendance->checkin_at, 'Asia/Jakarta')), 409);
                    $durationMinutes = (int) Carbon::parse($attendance->checkin_at, 'Asia/Jakarta')->diffInMinutes($claimed);
                    $flags = $attendance->flags ?? [];
                    $status = 'corrected';
                    if ($durationMinutes < Rules::int('min_work_duration_minutes')) {
                        $flags[] = 'Durasi kerja sangat singkat (< ' . Rules::int('min_work_duration_minutes') . ' menit): ' . $durationMinutes . ' menit';
                        $status = 'early_checkout';
                    }

                    $extra = max(0, (int) Carbon::parse($shift->end_at, 'Asia/Jakarta')->diffInMinutes($claimed, false));
                    $rawOvertime = max(0, $extra - Rules::int('overtime_threshold_minutes'));
                    $maxDailyOvertime = Rules::int('overtime_max_daily_minutes');
                    $overtime = min($maxDailyOvertime, $rawOvertime);
                    if ($rawOvertime > $maxDailyOvertime) {
                        $flags[] = 'Lembur melebihi batas legal harian (' . $maxDailyOvertime . ' menit / 4 jam), dibatasi ke ' . $maxDailyOvertime . ' menit';
                    }

                    $attendance->update([
                        'checkout_at' => $claimed,
                        'checkout_evidence' => ['exception_id' => $ex->id, 'approved_by' => $reviewer->id],
                        'status' => $status,
                        'overtime_minutes' => $overtime,
                        'flags' => $flags,
                    ]);
                }
            }

            $ex->update([
                'status' => $decision,
                'review_note' => $reviewNote,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now('Asia/Jakarta'),
            ]);

            Audit::record('attendance_exception', $ex->id, $decision, $reviewNote);
        });
    }

    public function correct(Attendance $attendance, array $data): void
    {
        $shift = $attendance->shift;
        abort_unless(Access::branch($shift->branch_id), 403);
        abort_if($attendance->user_id === auth()->id() && ! Access::admin(), 403, 'Manajer tidak dapat mengoreksi absensi sendiri.');
        Period::writable($shift->branch_id, $shift->start_at->toDateString());

        $checkin = $data['checkin_at'] ?? $attendance->checkin_at;
        $checkout = $data['checkout_at'] ?? $attendance->checkout_at;
        if ($data['status'] === 'absent') {
            $checkin = null;
            $checkout = null;
        }

        $late = $checkin ? max(0, (int) Carbon::parse($shift->start_at, 'Asia/Jakarta')->diffInMinutes(Carbon::parse($checkin, 'Asia/Jakarta'), false)) : 0;
        $extra = $checkout ? max(0, (int) Carbon::parse($shift->end_at, 'Asia/Jakarta')->diffInMinutes(Carbon::parse($checkout, 'Asia/Jakarta'), false)) : 0;

        $values = [
            'status' => $data['status'],
            'checkin_at' => $checkin,
            'checkout_at' => $checkout,
            'late_minutes' => $late,
            'late_units' => Rules::lateUnits($late),
            'overtime_minutes' => min(Rules::int('overtime_max_daily_minutes'), max(0, $extra - Rules::int('overtime_threshold_minutes'))),
            'overtime_approved_by' => null,
        ];

        $before = $attendance->toArray();
        $attendance->update($values);
        Audit::record('attendance', $attendance->id, 'correction', $data['reason'], $before, $values);
    }

    public function approveOvertime(Attendance $attendance, User $approver, string $reason): void
    {
        $shift = $attendance->shift;
        abort_unless(Access::branch($shift->branch_id), 403);
        abort_if($attendance->user_id === $approver->id && ! Access::admin(), 403, 'Manajer tidak dapat menyetujui lembur sendiri.');
        Period::writable($shift->branch_id, $shift->start_at->toDateString());
        abort_unless($attendance->checkout_at && $attendance->overtime_minutes > 0, 409);

        $attendance->update(['overtime_approved_by' => $approver->id]);
        Audit::record('attendance', $attendance->id, 'overtime_approve', $reason, null, ['minutes' => $attendance->overtime_minutes]);
    }

    private function reject(int $userId, ?int $shiftId, string $action, string $reason, array $evidence, Carbon $now): never
    {
        throw ValidationException::withMessages(['attendance' => $reason . '. Ajukan pengecualian jika perlu.']);
    }

    private function attempt(int $userId, ?int $shiftId, string $action, string $result, ?string $reason, array $evidence, Carbon $now): void
    {
        AttendanceAttempt::create([
            'user_id' => $userId,
            'shift_id' => $shiftId,
            'action' => $action,
            'result' => $result,
            'reason' => $reason,
            'evidence' => json_encode($evidence),
            'server_at' => $now,
        ]);
    }

    private static function distance(float $a, float $b, float $c, float $d): float
    {
        $r = 6371000;
        $lat = deg2rad($c - $a);
        $lon = deg2rad($d - $b);
        $x = sin($lat / 2) ** 2 + cos(deg2rad($a)) * cos(deg2rad($c)) * sin($lon / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($x)));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\ShiftResource;
use App\Http\Resources\UserResource;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * @group Profil Karyawan
 *
 * Endpoint data profil karyawan dan status kerja hari ini.
 */
class ProfileApiController extends Controller
{
    /**
     * Profil Lengkap
     *
     * Mengambil detail profil karyawan yang sedang login termasuk cabang dan jabatan.
     */
    public function profile(Request $request)
    {
        $user = $request->user()->load(['branch', 'position']);

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user),
        ]);
    }

    /**
     * Perbarui Profil
     *
     * Memperbarui nama tampilan akun yang sedang login. Data kepegawaian
     * (jabatan, cabang, gaji, status) tetap dikelola HR lewat web-admin.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
        ]);

        $user = $request->user();
        $user->update(['name' => $data['name']]);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => new UserResource($user->fresh()->load(['branch', 'position'])),
        ]);
    }

    /**
     * Status Hari Ini
     *
     * Menampilkan shift hari ini dan rekaman presensi saat ini.
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $now = now('Asia/Jakarta');
        $todayDate = $now->toDateString();

        // Cari shift hari ini atau shift aktif
        $shift = Shift::with(['branch', 'attendance', 'exceptions'])
            ->where('user_id', $user->id)
            ->whereDate('start_at', $todayDate)
            ->first();

        // Jika tidak ada shift hari ini, cari shift yang sedang berjalan (misal shift malam yang mulai kemarin)
        if (! $shift) {
            $shift = Shift::with(['branch', 'attendance', 'exceptions'])
                ->where('user_id', $user->id)
                ->where('start_at', '<=', $now)
                ->where('end_at', '>=', $now)
                ->first();
        }

        $attendance = $shift?->attendance;

        return response()->json([
            'success' => true,
            'data'    => [
                'server_time'       => $now->toIso8601String(),
                'server_timestamp'  => $now->timestamp,
                'has_shift'         => $shift !== null,
                'shift'             => $shift ? new ShiftResource($shift) : null,
                'attendance'        => $attendance ? new AttendanceResource($attendance) : null,
                'status'            => match (true) {
                    $shift === null                        => 'no_shift',
                    $shift->status !== 'approved'          => 'shift_not_approved',
                    $attendance?->checkout_at !== null     => 'completed',
                    $attendance?->checkin_at !== null      => 'checked_in',
                    default                                => 'not_checked_in',
                },
            ],
        ]);
    }
}

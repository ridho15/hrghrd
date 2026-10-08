<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group Auth
 *
 * Endpoint autentikasi dan manajemen sesi token Sanctum.
 */
class AuthApiController extends Controller
{
    /**
     * Login — Dapatkan Token API
     *
     * Autentikasi karyawan dengan email & password.
     * Mengembalikan token Sanctum yang digunakan sebagai Bearer token
     * di semua request API selanjutnya.
     *
     * @unauthenticated
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (! $user->active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
            ], 403);
        }

        // Tentukan abilities berdasarkan role
        $abilities = match ($user->role) {
            'admin'    => ['admin', 'manager', 'employee'],
            'manager'  => ['manager', 'employee'],
            default    => ['employee'],
        };

        // Hapus token lama dengan nama perangkat yang sama (opsional: bisa diatur)
        $user->tokens()->where('name', $data['device_name'])->delete();

        $token = $user->createToken($data['device_name'], $abilities);

        return response()->json([
            'success' => true,
            'data'    => [
                'token'      => $token->plainTextToken,
                'token_type' => 'Bearer',
                'abilities'  => $abilities,
                'user'       => new UserResource($user->load('branch', 'position')),
            ],
        ]);
    }

    /**
     * Logout — Cabut Token Aktif
     *
     * Mencabut token Bearer yang sedang digunakan.
     * Setelah logout, token tidak dapat digunakan lagi.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout. Token dicabut.',
        ]);
    }

    /**
     * Me — Profil Pengguna Saat Ini
     *
     * Mengembalikan data profil lengkap pengguna yang sedang terautentikasi,
     * beserta informasi token dan role.
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('branch', 'position');

        return response()->json([
            'success' => true,
            'data'    => [
                'user'      => new UserResource($user),
                'token_abilities' => $request->user()->currentAccessToken()->abilities,
            ],
        ]);
    }

    /**
     * Daftarkan Device Hash
     *
     * Mendaftarkan atau memperbarui device_hash untuk perangkat mobile.
     * Device hash diperlukan untuk validasi check-in presensi.
     * Format: hash unik perangkat (UUID, Android ID, atau IMEI hash).
     */
    public function registerDevice(Request $request)
    {
        $data = $request->validate([
            'device_hash' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $user = $request->user();

        // Disimpan sebagai SHA-256 agar formatnya konsisten dengan perbandingan
        // perangkat di AttendanceService::act(), yang membandingkan users.device_hash
        // terhadap hash('sha256', $deviceToken) saat check-in/check-out.
        $hashedDeviceHash = hash('sha256', $data['device_hash']);

        // Cek apakah device_hash sudah terdaftar di user lain
        $conflict = User::where('device_hash', $hashedDeviceHash)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Device ini sudah terdaftar di akun lain. Reset device terlebih dahulu.',
            ], 409);
        }

        $user->update(['device_hash' => $hashedDeviceHash]);

        return response()->json([
            'success' => true,
            'message' => 'Device berhasil didaftarkan.',
            'data'    => ['device_hash' => $data['device_hash']],
        ]);
    }

    /**
     * Reset Perangkat Presensi (Self-Service)
     *
     * Melepas ikatan device_hash milik akun sendiri, supaya perangkat berikutnya
     * yang login bisa dipakai presensi tanpa perlu bantuan admin. Mewajibkan
     * konfirmasi password karena ini melonggarkan kontrol anti-fraud presensi.
     */
    public function resetDevice(Request $request)
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Password salah.'],
            ]);
        }

        $user->update(['device_hash' => null]);
        Audit::record('user', $user->id, 'device_reset_self', 'Karyawan mereset sendiri perangkat presensi miliknya.');

        return response()->json([
            'success' => true,
            'message' => 'Perangkat presensi berhasil direset. Login ulang di perangkat yang ingin dipakai presensi.',
        ]);
    }

    /**
     * Ubah Password
     *
     * Mengubah password akun yang sedang login. Memerlukan password saat ini
     * sebagai konfirmasi. Mencabut token di perangkat lain (sesi saat ini
     * tetap aktif) sebagai tindakan keamanan standar setelah ganti password.
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'      => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $user->update(['password' => Hash::make($data['new_password'])]);

        $currentTokenId = $request->user()->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        Audit::record('user', $user->id, 'password_change_self', 'Karyawan mengubah password sendiri.');

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah. Sesi di perangkat lain telah dicabut demi keamanan.',
        ]);
    }

    /**
     * Refresh Token
     *
     * Merotasi token saat ini — token lama dicabut dan token baru dihasilkan.
     * Gunakan ini secara berkala untuk keamanan sesi jangka panjang.
     */
    public function refresh(Request $request)
    {
        $data = $request->validate([
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $oldAbilities = $request->user()->currentAccessToken()->abilities;

        // Cabut token saat ini
        $request->user()->currentAccessToken()->delete();

        // Buat token baru dengan abilities yang sama
        $token = $user->createToken($data['device_name'], $oldAbilities);

        return response()->json([
            'success' => true,
            'data'    => [
                'token'      => $token->plainTextToken,
                'token_type' => 'Bearer',
                'abilities'  => $oldAbilities,
            ],
        ]);
    }
}

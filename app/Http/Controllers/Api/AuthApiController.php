<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
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

        // Cek apakah device_hash sudah terdaftar di user lain
        $conflict = User::where('device_hash', $data['device_hash'])
            ->where('id', '!=', $user->id)
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Device ini sudah terdaftar di akun lain. Reset device terlebih dahulu.',
            ], 409);
        }

        $user->update(['device_hash' => $data['device_hash']]);

        return response()->json([
            'success' => true,
            'message' => 'Device berhasil didaftarkan.',
            'data'    => ['device_hash' => $user->device_hash],
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

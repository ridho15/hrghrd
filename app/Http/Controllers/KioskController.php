<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class KioskController extends Controller
{
    /**
     * Menampilkan halaman Kiosk QR mandiri tanpa menu navigasi.
     *
     * @param string|null $code Kode atau ID cabang (opsional)
     * @return View
     */
    public function show(?string $code = null): View
    {
        $branches = Branch::where('active', true)->orderBy('name')->get();
        abort_if($branches->isEmpty(), 404, 'Belum ada cabang aktif yang terdaftar.');

        $branch = null;
        if ($code !== null) {
            $branch = $branches->first(function (Branch $b) use ($code): bool {
                return strcasecmp($b->code, $code) === 0 || (string) $b->id === (string) $code;
            });
        }

        $branch = $branch ?: $branches->first();

        return view('kiosk-qr', compact('branch', 'branches'));
    }

    /**
     * Menyediakan data kode QR cabang yang berotasi otomatis setiap 30 detik.
     *
     * @param string $code Kode atau ID cabang
     * @return JsonResponse
     */
    public function code(string $code): JsonResponse
    {
        $branch = Branch::where('active', true)
            ->where(function ($q) use ($code): void {
                $q->where('code', $code)->orWhere('id', $code);
            })
            ->firstOrFail();

        $slot = intdiv(now()->timestamp, 30);
        $qrCode = AttendanceService::qr($branch, $slot);
        $expiresAt = ($slot + 1) * 30;
        $expiresIn = max(0, $expiresAt - now()->timestamp);

        return response()->json([
            'success' => true,
            'code' => $qrCode,
            'expires_at' => $expiresAt,
            'expires_in' => $expiresIn,
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
            ],
        ]);
    }
}

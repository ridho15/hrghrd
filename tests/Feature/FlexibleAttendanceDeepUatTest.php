<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlexibleAttendanceDeepUatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * UAT-1: Non-Happy Path - QR Code kedaluwarsa atau salah cabang ditolak.
     */
    public function test_flexible_checkin_rejected_with_invalid_or_expired_qr(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $this->actingAs($employee, 'sanctum');

        // 1. QR Code ngawur
        $res1 = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => '99999999',
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);
        $res1->assertStatus(422);
        $this->assertStringContainsString('Kode cabang kedaluwarsa atau salah', $res1->json('message'));

        // 2. QR Code dari cabang lain
        $otherBranch = $this->createBranch();
        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $otherBranchQr = AttendanceService::qr($otherBranch, $slot);

        $res2 = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $otherBranchQr,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);
        $res2->assertStatus(422);
        $this->assertStringContainsString('Kode cabang kedaluwarsa atau salah', $res2->json('message'));

        // 3. QR Code kedaluwarsa (dari 10 menit yang lalu)
        $expiredSlot = $slot - 5; // Lebih dari 2 slot rotasi
        $expiredQr = AttendanceService::qr($branch, $expiredSlot);

        $res3 = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $expiredQr,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);
        $res3->assertStatus(422);
        $this->assertStringContainsString('Kode cabang kedaluwarsa atau salah', $res3->json('message'));

        Carbon::setTestNow();
    }

    /**
     * UAT-2: Non-Happy Path - Deteksi Fake-GPS (mock location provider) diblokir total.
     */
    public function test_flexible_checkin_blocked_on_fake_gps_mock_location(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
            'is_mocked' => true,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Lokasi GPS terdeteksi palsu (mock location)', $response->json('message'));

        // Pastikan tidak ada shift atau presensi yang dibuat
        $this->assertDatabaseMissing('shifts', ['user_id' => $employee->id]);
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }

    /**
     * UAT-3: Non-Happy Path - Geofence di luar radius ditolak dengan informasi jarak.
     */
    public function test_flexible_checkin_rejected_outside_geofence_with_distance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

        $branch = $this->createBranch([
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_m' => 100,
        ]);
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employee, 'sanctum');

        // Koordinat berjarak ~1.1 km
        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => -6.2188,
            'longitude' => 106.8456,
            'accuracy' => 15,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Posisi terdeteksi berjarak', $response->json('message'));
        $this->assertStringContainsString('Batas radius cabang: 100 meter', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * UAT-4: Non-Happy Path - Akurasi sinyal GPS buruk (> 100m) ditolak.
     */
    public function test_flexible_checkin_rejected_on_poor_gps_accuracy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 150, // > 100m
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Akurasi sinyal GPS perangkat rendah', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * UAT-5: Non-Happy Path - Pengikatan perangkat (Device Binding): ditolak jika berganti HP.
     */
    public function test_flexible_device_binding_enforced(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employee, 'sanctum');

        // Check-in pertama dengan HP 1 -> Berhasil dan perangkat terkunci
        $res1 = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
            'device_token' => 'device_hp_original_123',
        ]);
        $res1->assertStatus(201);

        $employee->refresh();
        $this->assertNotNull($employee->device_hash);
        $this->assertSame(hash('sha256', 'device_hp_original_123'), $employee->device_hash);

        // Majukan waktu 1 jam
        Carbon::setTestNow(Carbon::parse('2026-10-16 11:00:00', 'Asia/Jakarta'));
        $slotOut = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $qrOut = AttendanceService::qr($branch, $slotOut);

        // Percobaan checkout dari HP lain (device token berbeda) -> Ditolak!
        $resForeignDevice = $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => $qrOut,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
            'device_token' => 'device_hp_teman_palsu_999',
        ]);

        $resForeignDevice->assertStatus(422);
        $this->assertStringContainsString('Perangkat berbeda', $resForeignDevice->json('message'));

        // Checkout dengan HP asli -> Berhasil
        $resOriginalDevice = $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => $qrOut,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
            'device_token' => 'device_hp_original_123',
        ]);
        $resOriginalDevice->assertOk();

        Carbon::setTestNow();
    }

    /**
     * UAT-6: Non-Happy Path - Check-out tanpa pernah check-in ditolak.
     */
    public function test_flexible_checkout_without_active_checkin_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => $validQr,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);

        // Karena tidak ada shift aktif, validasi shift_id required gagal (422)
        $response->assertStatus(422);

        Carbon::setTestNow();
    }

    /**
     * UAT-7: Happy Path - Penugasan panggilan tengah malam (Midnight On-Call 23:15 WIB).
     */
    public function test_flexible_midnight_on_call_shift(): void
    {
        // Panggilan darurat malam hari: 23:15 WIB
        Carbon::setTestNow(Carbon::parse('2026-10-16 23:15:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $slotIn = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $qrIn = AttendanceService::qr($branch, $slotIn);

        $this->actingAs($employee, 'sanctum');

        $resIn = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $qrIn,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);

        $resIn->assertStatus(201);

        $shift = Shift::where('user_id', $employee->id)->first();
        $this->assertSame('2026-10-16 23:15:00', $shift->start_at->toDateTimeString());

        $att = Attendance::where('shift_id', $shift->id)->first();
        $this->assertSame('present', $att->status);
        $this->assertSame(0, $att->late_minutes);

        // Tugas selesai dini hari berikutnya: 02:45:00 WIB (+3 jam 30 menit)
        Carbon::setTestNow(Carbon::parse('2026-10-17 02:45:00', 'Asia/Jakarta'));

        $slotOut = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $qrOut = AttendanceService::qr($branch, $slotOut);

        $resOut = $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => $qrOut,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);

        $resOut->assertOk();

        $shift->refresh();
        $this->assertSame('2026-10-17 02:45:00', $shift->end_at->toDateTimeString());

        $att->refresh();
        $this->assertSame('2026-10-17 02:45:00', Carbon::parse($att->checkout_at)->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * UAT-8: Happy Path - Dua penugasan terpisah di hari yang sama (Multi-Session On-Demand).
     */
    public function test_flexible_multiple_shifts_in_same_day(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);
        $this->actingAs($employee, 'sanctum');

        // === SESI 1: Pagi (08:00 - 11:00 WIB) ===
        Carbon::setTestNow(Carbon::parse('2026-10-16 08:00:00', 'Asia/Jakarta'));
        $qr1In = AttendanceService::qr($branch, intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS));
        $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $qr1In,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ])->assertStatus(201);

        Carbon::setTestNow(Carbon::parse('2026-10-16 11:00:00', 'Asia/Jakarta'));
        $qr1Out = AttendanceService::qr($branch, intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS));
        $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => $qr1Out,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ])->assertOk();

        // Verifikasi Sesi 1 selesai
        $this->assertCount(1, Shift::where('user_id', $employee->id)->get());
        $shift1 = Shift::where('user_id', $employee->id)->first();
        $this->assertSame('2026-10-16 08:00:00', $shift1->start_at->toDateTimeString());
        $this->assertSame('2026-10-16 11:00:00', $shift1->end_at->toDateTimeString());

        // === SESI 2: Sore dipanggil lagi (15:00 - 18:30 WIB) ===
        Carbon::setTestNow(Carbon::parse('2026-10-16 15:00:00', 'Asia/Jakarta'));
        $qr2In = AttendanceService::qr($branch, intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS));
        $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $qr2In,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ])->assertStatus(201);

        Carbon::setTestNow(Carbon::parse('2026-10-16 18:30:00', 'Asia/Jakarta'));
        $qr2Out = AttendanceService::qr($branch, intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS));
        $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => $qr2Out,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ])->assertOk();

        // Verifikasi sistem memiliki 2 shift mandiri untuk karyawan ini di hari yang sama
        $shifts = Shift::where('user_id', $employee->id)->orderBy('start_at')->get();
        $this->assertCount(2, $shifts);

        $shift2 = $shifts[1];
        $this->assertSame('2026-10-16 15:00:00', $shift2->start_at->toDateTimeString());
        $this->assertSame('2026-10-16 18:30:00', $shift2->end_at->toDateTimeString());

        Carbon::setTestNow();
    }
}

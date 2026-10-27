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

class RoamingFlexibleAttendanceDeepUatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * UAT-NHP-1: Non-Happy Path - Karyawan roaming scan QR code palsu / sampah (garbage QR).
     * Hasil yang diharapkan: 422 ditolak dan tidak ada shift maupun presensi yang terbentuk.
     */
    public function test_roaming_checkin_rejected_with_invalid_garbage_qr(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $roamingBranch = $this->createBranch([
            'name' => 'Cabang Bali',
            'latitude' => -8.6705,
            'longitude' => 115.2126,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => '00000000',
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Kode cabang kedaluwarsa atau salah', $response->json('message'));

        $this->assertDatabaseMissing('shifts', ['user_id' => $employee->id]);
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-2: Non-Happy Path - Karyawan roaming scan QR code cabang lain yang sudah kedaluwarsa (rotasi lama).
     */
    public function test_roaming_checkin_rejected_with_expired_qr_from_target_branch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $roamingBranch = $this->createBranch([
            'name' => 'Cabang Medan',
            'latitude' => 3.5952,
            'longitude' => 98.6722,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $expiredSlot = $slot - 5; // 15 menit yang lalu
        $expiredQr = AttendanceService::qr($roamingBranch, $expiredSlot);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $expiredQr,
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Kode cabang kedaluwarsa atau salah', $response->json('message'));

        $this->assertDatabaseMissing('shifts', ['user_id' => $employee->id]);
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-3: Non-Happy Path - Karyawan roaming mencoba presensi di cabang lain dengan Fake GPS (mock location).
     */
    public function test_roaming_checkin_blocked_on_fake_gps_mock_location(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $roamingBranch = $this->createBranch([
            'name' => 'Cabang Makassar',
            'latitude' => -5.1477,
            'longitude' => 119.4327,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $validQr = AttendanceService::qr($roamingBranch);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
            'is_mocked' => true,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Lokasi GPS terdeteksi palsu (mock location)', $response->json('message'));

        $this->assertDatabaseMissing('shifts', ['user_id' => $employee->id]);
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-4: Non-Happy Path - Karyawan roaming memiliki akurasi sinyal GPS buruk (> 100 meter).
     */
    public function test_roaming_checkin_rejected_on_poor_gps_accuracy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $roamingBranch = $this->createBranch(['name' => 'Cabang Semarang']);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $validQr = AttendanceService::qr($roamingBranch);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 150, // Akurasi sinyal buruk
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Akurasi sinyal GPS perangkat rendah', $response->json('message'));

        $this->assertDatabaseMissing('shifts', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-5: Non-Happy Path - Manipulasi silang (Cross-branch mismatch):
     * Karyawan scan QR cabang Surabaya, tetapi GPS-nya berada di Jakarta.
     */
    public function test_roaming_checkin_rejected_cross_branch_geofence_mismatch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch([
            'name' => 'Cabang Jakarta',
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'radius_m' => 100,
        ]);

        $surabayaBranch = $this->createBranch([
            'name' => 'Cabang Surabaya',
            'latitude' => -7.265757,
            'longitude' => 112.752090,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        // QR Surabaya tapi lokasi GPS ada di Jakarta (jarak ratusan km)
        $surabayaQr = AttendanceService::qr($surabayaBranch);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $surabayaQr,
            'latitude' => $homeBranch->latitude,
            'longitude' => $homeBranch->longitude,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Batas radius cabang', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-6: Non-Happy Path - Check-in ganda di dua cabang berbeda tanpa melakukan check-out terlebih dahulu.
     */
    public function test_roaming_employee_second_checkin_at_different_branch_without_checkout_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 09:00:00', 'Asia/Jakarta'));

        $branchA = $this->createBranch(['name' => 'Cabang A']);
        $branchB = $this->createBranch(['name' => 'Cabang B', 'latitude' => -6.2000, 'longitude' => 106.8000]);

        $employee = $this->createUser([
            'branch_id' => $branchA->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $this->actingAs($employee, 'sanctum');

        // Check-in pertama berhasil di Cabang A
        $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => AttendanceService::qr($branchA),
            'latitude' => $branchA->latitude,
            'longitude' => $branchA->longitude,
            'accuracy' => 10,
        ])->assertStatus(201);

        // Majukan waktu 1 jam ke 10:00
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        // Mencoba check-in kedua di Cabang B tanpa checkout dari Cabang A
        $res2 = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => AttendanceService::qr($branchB),
            'latitude' => $branchB->latitude,
            'longitude' => $branchB->longitude,
            'accuracy' => 10,
        ]);

        $res2->assertStatus(422);
        $this->assertStringContainsString('Check-in sudah tercatat', $res2->json('message'));

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-7: Non-Happy Path - Perangkat berbeda (Device Token mismatch).
     */
    public function test_roaming_employee_rejected_on_unrecognized_device(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $roamingBranch = $this->createBranch(['name' => 'Cabang Tujuan']);

        $registeredDeviceToken = 'valid_device_roaming_token_xyz';
        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
            'device_hash' => hash('sha256', $registeredDeviceToken),
        ]);

        $validQr = AttendanceService::qr($roamingBranch);

        $this->actingAs($employee, 'sanctum');

        // Mengirim device token yang salah / berbeda
        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
            'device_token' => 'attacker_different_device',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Perangkat berbeda', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-8: Non-Happy Path - Karyawan berstatus nonaktif (active = false) mencoba presensi roaming.
     */
    public function test_inactive_roaming_employee_cannot_checkin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
            'active' => false,
        ]);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('shifts', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-9: Non-Happy Path - Check-out roaming dengan QR code cabang yang salah.
     */
    public function test_roaming_checkout_rejected_with_invalid_qr(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 09:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $roamingBranch = $this->createBranch(['name' => 'Cabang Roaming']);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $this->actingAs($employee, 'sanctum');

        // Check-in berhasil di Cabang Roaming
        $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => AttendanceService::qr($roamingBranch),
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
        ])->assertStatus(201);

        // Majukan waktu ke 12:00
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', 'Asia/Jakarta'));

        // Check-out dengan QR ngawur
        $response = $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => '99999999',
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Kode cabang kedaluwarsa atau salah', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-10: Non-Happy Path - Karyawan Non-Roaming (allow_all_branches = false) mencoba scan QR cabang lain dengan prefix.
     * Sistem harus menolak karena karyawan terikat pada Cabang Induk (tidak memiliki izin roaming).
     */
    public function test_non_roaming_employee_rejected_when_trying_to_checkin_at_another_branch_with_prefixed_qr(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 09:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch([
            'name' => 'Cabang Induk Jakarta',
            'code' => 'JKT01',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_m' => 100,
        ]);

        $otherBranch = $this->createBranch([
            'name' => 'Cabang Bandung',
            'code' => 'BDG01',
            'latitude' => -6.9034,
            'longitude' => 107.6186,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => false, // Karyawan TIDAK diizinkan roaming
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $otherToken = AttendanceService::qr($otherBranch, $slot);
        $prefixedQr = "BDG01:{$otherToken}";

        $this->actingAs($employee, 'sanctum');

        // Check-in di Bandung dengan koordinat Bandung
        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $prefixedQr,
            'latitude' => $otherBranch->latitude,
            'longitude' => $otherBranch->longitude,
            'accuracy' => 10,
        ]);

        // Harus ditolak (karena fallback ke Cabang Induk Jakarta, jarak 150km dari Bandung)
        $response->assertStatus(422);
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-11: Non-Happy Path - Karyawan roaming scan QR dengan prefix cabang fiktif/tidak terdaftar (GHOST99:token).
     * Sistem harus menolak tanpa error 500 dan tidak mencatat presensi.
     */
    public function test_roaming_checkin_rejected_with_nonexistent_branch_prefix(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => 'GHOST99:84920173',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Kode cabang kedaluwarsa atau salah', $response->json('message'));
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-12: Non-Happy Path - Karyawan roaming scan QR dengan prefix cabang valid tapi tokennya kedaluwarsa.
     */
    public function test_roaming_checkin_rejected_with_prefixed_expired_token(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $roamingBranch = $this->createBranch([
            'name' => 'Cabang Solo',
            'code' => 'SOL01',
            'latitude' => -7.5755,
            'longitude' => 110.8243,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $expiredToken = AttendanceService::qr($roamingBranch, $slot - 4); // 12 menit lalu
        $expiredPrefixedQr = "SOL01:{$expiredToken}";

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $expiredPrefixedQr,
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Kode cabang kedaluwarsa atau salah', $response->json('message'));
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-13: Non-Happy Path - Karyawan roaming scan QR cabang tujuan valid, tapi GPS berada 3 km di luar radius cabang tujuan tersebut.
     */
    public function test_roaming_checkin_rejected_when_gps_is_3km_away_from_roaming_branch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $roamingBranch = $this->createBranch([
            'name' => 'Cabang Bali Kuta',
            'code' => 'DPS01',
            'latitude' => -8.7210,
            'longitude' => 115.1695,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validToken = AttendanceService::qr($roamingBranch, $slot);
        $validQr = "DPS01:{$validToken}";

        $this->actingAs($employee, 'sanctum');

        // Koordinat GPS digeser ~3 km (sekitar 0.027 derajat latitude)
        $farLatitude = $roamingBranch->latitude + 0.027;
        $farLongitude = $roamingBranch->longitude;

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $farLatitude,
            'longitude' => $farLongitude,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Batas radius cabang', $response->json('message'));
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }

    /**
     * UAT-NHP-14: Non-Happy Path Web Route - Presensi roaming ditolak jika attendance challenge sesi salah.
     */
    public function test_roaming_web_checkin_rejected_on_invalid_session_challenge(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 09:30:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Kantor Pusat']);
        $roamingBranch = $this->createBranch(['name' => 'Cabang Bogor', 'code' => 'BGR01']);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $this->actingAs($employee);
        session(['attendance_challenge' => 'correct_secret_challenge']);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $token = AttendanceService::qr($roamingBranch, $slot);
        $prefixedQr = "BGR01:{$token}";

        $response = $this->withCookie('hrd_device', 'web_cookie_device')->post('/attendance/0', [
            'action' => 'in',
            'qr_code' => $prefixedQr,
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
            'challenge' => 'attacker_manipulated_challenge',
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        Carbon::setTestNow();
    }
}

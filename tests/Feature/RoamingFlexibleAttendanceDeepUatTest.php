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
}

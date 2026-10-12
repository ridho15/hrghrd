<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceAttempt;
use App\Models\Branch;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QrThreeMinuteRotationDeepUnhappyPathTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Unhappy Path: QR dari 2 slot sebelumnya (>= 360 detik lalu) harus ditolak tegas.
     */
    public function test_checkin_with_slot_older_than_grace_window_is_strictly_rejected(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_123')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        // Slot 2 putaran lalu (360 detik / 6 menit yang lalu)
        $currentSlot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $staleSlot = $currentSlot - 2;
        $staleQr = AttendanceService::qr($branch, $staleSlot);

        $response = $this->withCookie('hrd_device', 'dev_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => $staleQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);

        // Pastikan terekam di log upaya presensi gagal
        $this->assertDatabaseHas('attendance_attempts', [
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'action' => 'in',
            'reason' => 'Kode cabang kedaluwarsa atau salah. Ajukan pengecualian jika perlu.',
        ]);
    }

    /**
     * Unhappy Path: Penyerang mencoba menebak / mengirim QR slot masa depan (+1 slot).
     */
    public function test_checkin_with_future_slot_qr_code_is_strictly_rejected(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_123')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        // Slot masa depan (+1 slot / 3 menit ke depan)
        $currentSlot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $futureSlot = $currentSlot + 1;
        $futureQr = AttendanceService::qr($branch, $futureSlot);

        $response = $this->withCookie('hrd_device', 'dev_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => $futureQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
        $this->assertDatabaseHas('attendance_attempts', [
            'user_id' => $employee->id,
            'reason' => 'Kode cabang kedaluwarsa atau salah. Ajukan pengecualian jika perlu.',
        ]);
    }

    /**
     * Boundary Path: Slot saat ini dan slot -1 (grace period) valid, tetapi slot -2 gagal.
     */
    public function test_checkin_within_grace_slot_minus_one_succeeds_but_minus_two_fails(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee1 = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_emp1')]);
        $employee2 = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_emp2')]);

        $shift1 = $this->createShift($employee1, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->subMinutes(5)->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);
        $shift2 = $this->createShift($employee2, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->subMinutes(5)->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $currentSlot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);

        // 1. Employee 1 memakai slot -1 (grace period: 1 slot sebelum rotasi) -> Berhasil
        $graceQr = AttendanceService::qr($branch, $currentSlot - 1);
        $this->actingAs($employee1);
        $chal1 = 'chal_1_' . uniqid();
        session(['attendance_challenge' => $chal1]);

        $res1 = $this->withCookie('hrd_device', 'dev_emp1')->post("/attendance/{$shift1->id}", [
            'action' => 'in',
            'qr_code' => $graceQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $chal1,
        ]);

        $res1->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendances', [
            'shift_id' => $shift1->id,
            'user_id' => $employee1->id,
            'status' => 'present',
        ]);

        // 2. Employee 2 memakai slot -2 (lewat dari grace period) -> Gagal
        $expiredQr = AttendanceService::qr($branch, $currentSlot - 2);
        $this->actingAs($employee2);
        $chal2 = 'chal_2_' . uniqid();
        session(['attendance_challenge' => $chal2]);

        $res2 = $this->withCookie('hrd_device', 'dev_emp2')->post("/attendance/{$shift2->id}", [
            'action' => 'in',
            'qr_code' => $expiredQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $chal2,
        ]);

        $res2->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift2->id]);
    }

    /**
     * Unhappy Path: Tampered QR payload (string kosong, bukan 8 digit, karakter aneh, dsb).
     */
    public function test_checkin_with_tampered_qr_code_payloads_is_rejected(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_123')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $invalidPayloads = [
            '1234567',          // 7 digit (kurang 1)
            '123456789',        // 9 digit (lebih 1)
            'abcdefgh',         // huruf
            '!@#$%^&*',         // simbol
            '        ',         // spasi
            '',                 // kosong
            ' 12345678 ',       // leading/trailing space
        ];

        $this->actingAs($employee);

        foreach ($invalidPayloads as $badQr) {
            $chal = 'chal_' . uniqid();
            session(['attendance_challenge' => $chal]);

            $response = $this->withCookie('hrd_device', 'dev_123')->post("/attendance/{$shift->id}", [
                'action' => 'in',
                'qr_code' => $badQr,
                'latitude' => -6.175392,
                'longitude' => 106.827153,
                'accuracy' => 10,
                'challenge' => $chal,
            ]);

            // Harus gagal, baik di level form request ('qr_code') atau di service ('attendance')
            $this->assertTrue(
                $response->getSession()->has('errors'),
                "Harus terdapat validation error untuk input QR: '{$badQr}'"
            );
        }

        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
    }

    /**
     * Unhappy Path: Cross-Branch Replay Attack.
     * Menggunakan QR cabang B untuk check-in pada shift di cabang A.
     */
    public function test_cross_branch_qr_replay_is_strictly_rejected(): void
    {
        $branchA = $this->createBranch(['name' => 'Cabang A', 'code' => 'CBA', 'latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $branchB = $this->createBranch(['name' => 'Cabang B', 'code' => 'CBB', 'latitude' => -6.200000, 'longitude' => 106.800000, 'radius_m' => 100]);

        $employee = $this->createUser(['branch_id' => $branchA->id, 'device_hash' => hash('sha256', 'dev_123')]);
        $shift = $this->createShift($employee, $branchA, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        // QR valid dari cabang B pada slot saat ini
        $branchBQr = AttendanceService::qr($branchB);

        $response = $this->withCookie('hrd_device', 'dev_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => $branchBQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
    }

    /**
     * Security / Unhappy Path: Kiosk QR endpoint menolak cabang yang tidak aktif (active = false).
     */
    public function test_kiosk_qr_endpoint_returns_404_for_inactive_branch(): void
    {
        $inactiveBranch = $this->createBranch(['code' => 'INACTIVE', 'active' => false]);

        $response = $this->get(route('kiosk.qr.code', $inactiveBranch->code));

        $response->assertNotFound();
    }

    /**
     * Security / Unhappy Path: API QR endpoint dilarang untuk role employee (403)
     * dan manager cabang lain (403).
     */
    public function test_api_qr_endpoint_authorization_negative_checks(): void
    {
        $branchA = $this->createBranch(['code' => 'BRA']);
        $branchB = $this->createBranch(['code' => 'BRB']);

        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branchA->id]);
        $managerA = $this->createUser(['role' => 'manager', 'branch_id' => $branchA->id]);

        // 1. Employee biasa mencoba mengakses generator QR
        Sanctum::actingAs($employee, ['employee']);
        $this->getJson('/api/v1/attendance/qr?branch_id=' . $branchA->id)->assertStatus(403);

        // 2. Manager cabang A mencoba menghasilkan QR untuk cabang B (bukan wewenangnya)
        Sanctum::actingAs($managerA, ['manager']);
        $this->getJson('/api/v1/attendance/qr?branch_id=' . $branchB->id)->assertStatus(403);
    }

    /**
     * Timing & Boundary Verification: expires_in dan expires_at selalu terikat interval 180s.
     */
    public function test_kiosk_qr_expires_in_never_negative_and_bounded_by_interval(): void
    {
        $branch = $this->createBranch(['code' => 'BOUND01']);

        $response = $this->get(route('kiosk.qr.code', 'BOUND01'));

        $response->assertOk();
        $data = $response->json();

        $this->assertGreaterThan(0, $data['expires_in']);
        $this->assertLessThanOrEqual(AttendanceService::QR_INTERVAL_SECONDS, $data['expires_in']);
        $this->assertEquals(0, $data['expires_at'] % AttendanceService::QR_INTERVAL_SECONDS);
        $this->assertEquals($data['expires_at'] - now()->timestamp, $data['expires_in']);
    }

    /**
     * Unhappy Path / Replay Attack: Karyawan tidak dapat melakukan check-in ganda
     * dalam slot 3 menit yang sama.
     */
    public function test_replay_attack_in_same_three_minute_slot_cannot_checkin_twice(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_123')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->subMinutes(5)->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $validQr = AttendanceService::qr($branch);

        // Check-in pertama -> Berhasil
        $chal1 = 'chal_1_' . uniqid();
        session(['attendance_challenge' => $chal1]);

        $res1 = $this->withCookie('hrd_device', 'dev_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => $validQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $chal1,
        ]);

        $res1->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendances', ['shift_id' => $shift->id, 'user_id' => $employee->id]);

        // Check-in kedua dengan QR yang sama pada slot yang sama -> Ditolak (sudah check-in)
        $chal2 = 'chal_2_' . uniqid();
        session(['attendance_challenge' => $chal2]);

        $res2 = $this->withCookie('hrd_device', 'dev_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => $validQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $chal2,
        ]);

        $res2->assertSessionHasErrors('attendance');
    }

    /**
     * Unhappy Path: Check-out tanpa check-in terlebih dahulu harus ditolak meskipun QR valid.
     */
    public function test_checkout_without_prior_checkin_is_rejected_even_with_valid_qr(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_123')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->subHours(4)->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(4)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $validQr = AttendanceService::qr($branch);
        $chal = 'chal_' . uniqid();
        session(['attendance_challenge' => $chal]);

        $response = $this->withCookie('hrd_device', 'dev_123')->post("/attendance/{$shift->id}", [
            'action' => 'out',
            'qr_code' => $validQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $chal,
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
    }
}

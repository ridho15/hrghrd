<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceAttempt;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceAntiFraudNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_checkin_with_mismatched_session_challenge_is_rejected(): void
    {
        // Memastikan alur web/kiosk (sesi cookie browser, berbeda dari API mobile
        // Bearer-token) masih benar-benar memverifikasi `attendance_challenge` dan
        // menolak bila tidak cocok dengan yang ditaruh di sesi — tidak ikut hilang
        // saat AttendanceApiController berhenti memaksakan pengecekan ini.
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_token_123')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        session(['attendance_challenge' => 'challenge_from_page_load']);

        $response = $this->withCookie('device_token', 'dev_token_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => 'a_completely_different_challenge',
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
    }

    public function test_checkin_on_draft_shift_is_rejected(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'draft', // DRAFT
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        $response = $this->withCookie('device_token', 'dev_token_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 15,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseHas('attendance_attempts', [
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'result' => 'rejected',
        ]);
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
    }

    public function test_double_checkin_is_rejected(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_token_123')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        // Existing checkin already recorded
        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'checkin_at' => now('Asia/Jakarta'),
            'status' => 'present',
            'late_minutes' => 0,
            'late_units' => 0,
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        $response = $this->withCookie('device_token', 'dev_token_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
    }

    public function test_checkout_without_prior_checkin_is_rejected(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_token_123')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->subHours(4)->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(4)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        // Attempting check-out directly without check-in
        $response = $this->withCookie('device_token', 'dev_token_123')->post("/attendance/{$shift->id}", [
            'action' => 'out',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
    }

    public function test_attendance_with_different_device_is_rejected(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        // Bound to device_token_ORIGINAL
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'device_hash' => hash('sha256', 'device_token_ORIGINAL'),
        ]);

        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        // Attacker uses DIFFERENT device token
        $response = $this->withCookie('device_token', 'device_token_SPOOFED')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
    }

    public function test_attendance_outside_geofence_radius_is_rejected(): void
    {
        // Branch at Monas, Jakarta: -6.175392, 106.827153 (radius: 100m)
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_token_123')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        // Coordinate ~800m away
        $response = $this->withCookie('device_token', 'dev_token_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.182000,
            'longitude' => 106.827153,
            'accuracy' => 20,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseHas('attendance_attempts', [
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'result' => 'rejected',
        ]);
    }

    public function test_expired_qr_code_is_rejected(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_token_123')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        // Stale QR from 5 slots ago (150 seconds ago)
        $staleSlot = intdiv(time(), 30) - 5;
        $staleQr = AttendanceService::qr($branch, $staleSlot);

        $response = $this->withCookie('device_token', 'dev_token_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => $staleQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
    }

    public function test_checkin_with_mismatched_branch_qr_code_is_rejected(): void
    {
        $branchA = $this->createBranch(['name' => 'Cabang A', 'code' => 'CBA', 'latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $branchB = $this->createBranch(['name' => 'Cabang B', 'code' => 'CBB', 'latitude' => -6.200000, 'longitude' => 106.800000, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branchA->id, 'device_hash' => hash('sha256', 'dev_token_123')]);
        $shift = $this->createShift($employee, $branchA, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        // Scanning rotating QR from Branch B for a shift at Branch A
        $wrongBranchQr = AttendanceService::qr($branchB);

        $response = $this->withCookie('hrd_device', 'dev_token_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => $wrongBranchQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
        $this->assertDatabaseHas('attendance_attempts', [
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'result' => 'rejected',
        ]);
    }

    public function test_checkin_when_employee_branch_differs_from_shift_branch_is_rejected(): void
    {
        $branchA = $this->createBranch(['name' => 'Cabang A', 'code' => 'CBA', 'latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $branchB = $this->createBranch(['name' => 'Cabang B', 'code' => 'CBB', 'latitude' => -6.200000, 'longitude' => 106.800000, 'radius_m' => 100]);
        // Employee assigned to Branch A
        $employee = $this->createUser(['branch_id' => $branchA->id, 'device_hash' => hash('sha256', 'dev_token_123')]);
        // Shift scheduled for Branch B
        $shift = $this->createShift($employee, $branchB, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        $response = $this->withCookie('hrd_device', 'dev_token_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branchB),
            'latitude' => -6.200000,
            'longitude' => 106.800000,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
        $attempt = AttendanceAttempt::where('shift_id', $shift->id)->where('user_id', $employee->id)->latest('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('rejected', $attempt->result);
        $this->assertStringContainsString('Cabang shift tidak sesuai', $attempt->reason);
    }

    public function test_checkin_when_late_beyond_limit_is_rejected_without_creating_attendance(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_token_123')]);
        // Shift started 75 minutes ago (> 60m late_reject_minutes)
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->subMinutes(75)->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(6)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        $response = $this->withCookie('hrd_device', 'dev_token_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
        // Critical: NO attendance record created in attendances table
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
        $attempt = AttendanceAttempt::where('shift_id', $shift->id)->where('user_id', $employee->id)->latest('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('rejected', $attempt->result);
        $this->assertStringContainsString('Batas waktu keterlambatan', $attempt->reason);
    }

    public function test_checkin_when_shift_already_ended_is_rejected(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_token_123')]);
        // Shift ended 15 minutes ago
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->subHours(9)->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->subMinutes(15)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        $challenge = 'chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        $response = $this->withCookie('hrd_device', 'dev_token_123')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
        $attempt = AttendanceAttempt::where('shift_id', $shift->id)->where('user_id', $employee->id)->latest('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('rejected', $attempt->result);
        $this->assertStringContainsString('Shift telah selesai', $attempt->reason);
    }

    public function test_checkout_requires_valid_qr_code(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id, 'device_hash' => hash('sha256', 'dev_token_checkout')]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->subHours(4)->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(4)->format('Y-m-d H:i:s'),
        ]);

        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'checkin_at' => now('Asia/Jakarta')->subHours(4),
            'status' => 'present',
        ]);

        Sanctum::actingAs($employee, ['employee']);

        // Check-out without qr_code should fail validation (HTTP 422)
        $resNoQr = $this->postJson('/api/v1/attendance/checkout', [
            'shift_id' => $shift->id,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'device_token' => 'dev_token_checkout',
        ]);
        $resNoQr->assertStatus(422)->assertJsonValidationErrors('qr_code');

        // Check-out with invalid qr_code should fail anti-fraud
        $resBadQr = $this->postJson('/api/v1/attendance/checkout', [
            'shift_id' => $shift->id,
            'qr_code' => 'WRONGQR8',
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'device_token' => 'dev_token_checkout',
        ]);
        $resBadQr->assertStatus(422)->assertJsonPath('success', false);

        // Check-out with valid rotating qr_code succeeds
        $resValid = $this->postJson('/api/v1/attendance/checkout', [
            'shift_id' => $shift->id,
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'device_token' => 'dev_token_checkout',
        ]);
        $resValid->assertStatus(200)->assertJsonPath('success', true);
    }
}

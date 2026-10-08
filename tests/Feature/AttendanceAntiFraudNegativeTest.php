<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceAttempt;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

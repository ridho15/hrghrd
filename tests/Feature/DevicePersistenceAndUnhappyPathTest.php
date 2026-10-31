<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceAttempt;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicePersistenceAndUnhappyPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_without_cookie_or_token_generates_new_device_cookie(): void
    {
        $employee = $this->createUser(['password' => bcrypt('secret123')]);

        $response = $this->post('/login', [
            'login' => $employee->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertCookie('hrd_device');
    }

    public function test_login_with_device_token_from_localstorage_restores_same_cookie(): void
    {
        $knownToken = 'persisted_localstorage_token_xyz_12345';
        $employee = $this->createUser([
            'password' => bcrypt('secret123'),
            'device_hash' => hash('sha256', $knownToken),
        ]);

        // Simulasi cookie browser terhapus, tetapi kirim device_token dari localStorage
        $response = $this->post('/login', [
            'login' => $employee->email,
            'password' => 'secret123',
            'device_token' => $knownToken,
        ]);

        $response->assertRedirect(route('home'));
        $response->assertCookie('hrd_device', $knownToken);
    }

    public function test_attendance_with_device_token_fallback_succeeds_when_cookie_is_missing(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $knownToken = 'persistent_device_token_morning_shift';
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'device_hash' => hash('sha256', $knownToken),
        ]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        session(['attendance_challenge' => 'valid_challenge_123']);

        // Request TANPA cookie 'hrd_device', tetapi mengirim 'device_token' di payload POST
        $response = $this->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => 'valid_challenge_123',
            'device_token' => $knownToken,
        ]);

        $response->assertSessionHas('ok', 'Check-in tercatat.');
        $response->assertCookie('hrd_device', $knownToken);

        $this->assertDatabaseHas('attendances', [
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
        ]);
    }

    public function test_unhappy_path_attendance_from_truly_different_device_is_rejected(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $registeredToken = 'original_registered_device_token';
        $differentToken = 'completely_different_device_token_attacker';

        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'device_hash' => hash('sha256', $registeredToken),
        ]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        session(['attendance_challenge' => 'valid_challenge_456']);

        // Kirim request dari token perangkat berbeda baik via cookie maupun input
        $response = $this->withCookie('hrd_device', $differentToken)->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => 'valid_challenge_456',
            'device_token' => $differentToken,
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);

        // Pastikan dicatat di log penolakan dengan alasan 'Perangkat berbeda'
        $this->assertDatabaseHas('attendance_attempts', [
            'user_id' => $employee->id,
            'result' => 'rejected',
        ]);
        $latestAttempt = AttendanceAttempt::where('user_id', $employee->id)->latest('id')->first();
        $this->assertStringContainsString('Perangkat berbeda', $latestAttempt->reason);
    }

    public function test_unhappy_path_attendance_without_any_cookie_or_token_is_rejected_for_bound_user(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'device_hash' => hash('sha256', 'registered_phone_123'),
        ]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($employee);
        session(['attendance_challenge' => 'valid_challenge_789']);

        // Tanpa cookie dan tanpa device_token di payload
        $response = $this->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => 'valid_challenge_789',
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);

        $latestAttempt = AttendanceAttempt::where('user_id', $employee->id)->latest('id')->first();
        $this->assertStringContainsString('Perangkat berbeda', $latestAttempt->reason);
    }

    public function test_admin_reset_allows_user_to_rebind_new_device_successfully(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $oldToken = 'old_device_token';
        $newToken = 'new_replacement_phone_token';

        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'device_hash' => hash('sha256', $oldToken),
        ]);
        $shift = $this->createShift($employee, $branch, [
            'status' => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'end_at' => now('Asia/Jakarta')->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        // Langkah 1: Coba pakai device baru -> ditolak
        $this->actingAs($employee);
        session(['attendance_challenge' => 'chal_1']);
        $resFailed = $this->withCookie('hrd_device', $newToken)->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => 'chal_1',
            'device_token' => $newToken,
        ]);
        $resFailed->assertSessionHasErrors('attendance');

        // Langkah 2: Admin melakukan reset perangkat
        $admin = $this->createUser(['role' => 'admin', 'branch_id' => null]);
        $this->actingAs($admin);
        $this->post("/people/{$employee->id}", [
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'branch_id' => $employee->branch_id,
            'position_id' => $employee->position_id,
            'hired_at' => $employee->hired_at,
            'base_salary' => $employee->base_salary,
            'reset_device' => '1',
        ]);

        $employee->refresh();
        $this->assertNull($employee->device_hash);

        // Langkah 3: Karyawan absen kembali dari device baru -> sekarang sukses & otomatis bind token baru
        $this->actingAs($employee);
        session(['attendance_challenge' => 'chal_2']);
        $resSuccess = $this->withCookie('hrd_device', $newToken)->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => 'chal_2',
            'device_token' => $newToken,
        ]);

        $resSuccess->assertSessionHas('ok', 'Check-in tercatat.');
        $employee->refresh();
        $this->assertEquals(hash('sha256', $newToken), $employee->device_hash);
    }
}

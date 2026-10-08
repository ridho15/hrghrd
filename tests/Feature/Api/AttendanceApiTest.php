<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Shift;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_and_today_endpoints(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);
        Sanctum::actingAs($employee, ['employee']);

        // Profile endpoint
        $resProfile = $this->getJson('/api/v1/profile');
        $resProfile->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $employee->id);

        // Today endpoint with no shift
        $resToday = $this->getJson('/api/v1/profile/today');
        $resToday->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'no_shift');

        // Create approved shift today
        $shift = $this->createShift($employee, $branch, [
            'status'   => 'approved',
            'start_at' => now('Asia/Jakarta')->format('Y-m-d 08:00:00'),
            'end_at'   => now('Asia/Jakarta')->format('Y-m-d 17:00:00'),
        ]);

        $resToday2 = $this->getJson('/api/v1/profile/today');
        $resToday2->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_shift', true)
            ->assertJsonPath('data.status', 'not_checked_in');
    }

    public function test_update_own_profile_name(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id, 'name' => 'Nama Lama']);
        Sanctum::actingAs($employee, ['employee']);

        $response = $this->patchJson('/api/v1/profile', ['name' => 'Nama Baru Setelah Edit']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Nama Baru Setelah Edit');

        $this->assertEquals('Nama Baru Setelah Edit', $employee->fresh()->name);
    }

    public function test_qr_generation_manager_access(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        // Employee should be forbidden
        Sanctum::actingAs($employee, ['employee']);
        $this->getJson('/api/v1/attendance/qr?branch_id=' . $branch->id)->assertStatus(403);

        // Manager should get valid QR code
        Sanctum::actingAs($manager, ['manager']);
        $res = $this->getJson('/api/v1/attendance/qr?branch_id=' . $branch->id);
        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['code', 'expires_at', 'expires_in', 'branch'],
            ]);
    }

    public function test_checkin_succeeds_without_any_session_challenge(): void
    {
        // Regresi: klien mobile Bearer-token tidak membawa cookie sesi antar-request
        // (dibuktikan langsung: dua request /profile/today berurutan tanpa cookie jar
        // menghasilkan nilai attendance_challenge yang berbeda setiap kali). Endpoint
        // API check-in/check-out karenanya harus sukses walau sesi `attendance_challenge`
        // tidak pernah diisi sama sekali dan request tidak mengirim field `challenge`.
        // Sebelumnya endpoint ini "membantu" dengan menimpa sesi memakai nilai yang baru
        // saja dikirim client lalu membandingkannya terhadap nilai itu sendiri — selalu
        // lolos secara trivial, tapi tidak pernah benar-benar memverifikasi apa pun.
        $branch = $this->createBranch([
            'latitude'  => -6.175392,
            'longitude' => 106.827153,
            'radius_m'  => 100,
        ]);
        $deviceToken = 'test_token_no_challenge';
        $employee = $this->createUser([
            'branch_id'   => $branch->id,
            'device_hash' => hash('sha256', $deviceToken),
        ]);

        $shift = $this->createShift($employee, $branch, [
            'status'   => 'approved',
            'start_at' => now('Asia/Jakarta')->subMinutes(5)->format('Y-m-d H:i:s'),
            'end_at'   => now('Asia/Jakarta')->addHours(7)->format('Y-m-d H:i:s'),
        ]);

        Sanctum::actingAs($employee, ['employee']);

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'shift_id'     => $shift->id,
            'qr_code'      => AttendanceService::qr($branch),
            'latitude'     => -6.175392,
            'longitude'    => 106.827153,
            'accuracy'     => 10,
            'device_token' => $deviceToken,
            // Sengaja TIDAK mengirim 'challenge' sama sekali, dan sesi tidak pernah
            // diisi di test ini — meniru request mobile sungguhan.
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
    }

    public function test_checkin_successful_with_qr_and_geofence(): void
    {
        $branch = $this->createBranch([
            'latitude'  => -6.175392,
            'longitude' => 106.827153,
            'radius_m'  => 100,
        ]);
        $deviceToken = 'test_token_mobile_123';
        $employee = $this->createUser([
            'branch_id'   => $branch->id,
            'device_hash' => hash('sha256', $deviceToken),
        ]);

        $shift = $this->createShift($employee, $branch, [
            'status'   => 'approved',
            'start_at' => now('Asia/Jakarta')->subMinutes(5)->format('Y-m-d H:i:s'),
            'end_at'   => now('Asia/Jakarta')->addHours(7)->format('Y-m-d H:i:s'),
        ]);

        Sanctum::actingAs($employee, ['employee']);

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'shift_id'     => $shift->id,
            'qr_code'      => AttendanceService::qr($branch),
            'latitude'     => -6.175392,
            'longitude'    => 106.827153,
            'accuracy'     => 10,
            'device_token' => $deviceToken,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shift_id', $shift->id);

        $this->assertDatabaseHas('attendances', [
            'shift_id' => $shift->id,
            'user_id'  => $employee->id,
            'status'   => 'present',
        ]);
    }

    public function test_checkin_succeeds_after_real_device_registration_flow(): void
    {
        // Regresi: reproduksi end-to-end persis seperti aplikasi Flutter (login lalu
        // memanggil POST /auth/device secara nyata sebelum check-in), bukan men-seed
        // device_hash langsung ke database seperti test lain di atas. Sebelumnya,
        // AuthApiController::registerDevice() menyimpan device_hash mentah sementara
        // AttendanceService::act() membandingkannya terhadap hash SHA-256 dari device
        // token, sehingga check-in SELALU gagal "Perangkat berbeda" setelah login normal.
        $branch = $this->createBranch([
            'latitude'  => -6.175392,
            'longitude' => 106.827153,
            'radius_m'  => 100,
        ]);
        $deviceToken = 'mob_real_flow_regression_test';
        $employee = $this->createUser([
            'branch_id'   => $branch->id,
            'device_hash' => null,
        ]);

        $shift = $this->createShift($employee, $branch, [
            'status'   => 'approved',
            'start_at' => now('Asia/Jakarta')->subMinutes(5)->format('Y-m-d H:i:s'),
            'end_at'   => now('Asia/Jakarta')->addHours(7)->format('Y-m-d H:i:s'),
        ]);

        Sanctum::actingAs($employee, ['employee']);

        // Persis seperti AuthProvider.login() di Flutter: dipanggil otomatis setelah login.
        $this->postJson('/api/v1/auth/device', [
            'device_hash' => $deviceToken,
        ])->assertStatus(200)->assertJsonPath('success', true);

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'shift_id'     => $shift->id,
            'qr_code'      => AttendanceService::qr($branch),
            'latitude'     => -6.175392,
            'longitude'    => 106.827153,
            'accuracy'     => 10,
            'device_token' => $deviceToken,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shift_id', $shift->id);

        $this->assertDatabaseHas('attendances', [
            'shift_id' => $shift->id,
            'user_id'  => $employee->id,
        ]);
    }

    public function test_checkin_rejected_when_location_is_mocked(): void
    {
        $branch = $this->createBranch([
            'latitude'  => -6.175392,
            'longitude' => 106.827153,
            'radius_m'  => 100,
        ]);
        $deviceToken = 'test_token_mock_gps';
        $employee = $this->createUser([
            'branch_id'   => $branch->id,
            'device_hash' => hash('sha256', $deviceToken),
        ]);

        $shift = $this->createShift($employee, $branch, [
            'status'   => 'approved',
            'start_at' => now('Asia/Jakarta')->subMinutes(5)->format('Y-m-d H:i:s'),
            'end_at'   => now('Asia/Jakarta')->addHours(7)->format('Y-m-d H:i:s'),
        ]);

        Sanctum::actingAs($employee, ['employee']);

        // Koordinat PERSIS di titik cabang (lolos jarak & akurasi), tapi ditandai
        // berasal dari aplikasi fake-GPS/mock location provider.
        $response = $this->postJson('/api/v1/attendance/checkin', [
            'shift_id'     => $shift->id,
            'qr_code'      => AttendanceService::qr($branch),
            'latitude'     => -6.175392,
            'longitude'    => 106.827153,
            'accuracy'     => 5,
            'is_mocked'    => true,
            'device_token' => $deviceToken,
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
    }

    public function test_checkin_rejected_when_outside_geofence(): void
    {
        $branch = $this->createBranch([
            'latitude'  => -6.175392,
            'longitude' => 106.827153,
            'radius_m'  => 50,
        ]);
        $deviceToken = 'test_token_mobile_456';
        $employee = $this->createUser([
            'branch_id'   => $branch->id,
            'device_hash' => hash('sha256', $deviceToken),
        ]);

        $shift = $this->createShift($employee, $branch, [
            'status'   => 'approved',
            'start_at' => now('Asia/Jakarta')->subMinutes(5)->format('Y-m-d H:i:s'),
            'end_at'   => now('Asia/Jakarta')->addHours(7)->format('Y-m-d H:i:s'),
        ]);

        Sanctum::actingAs($employee, ['employee']);

        // Koordinat jauh (~10 km)
        $response = $this->postJson('/api/v1/attendance/checkin', [
            'shift_id'     => $shift->id,
            'qr_code'      => AttendanceService::qr($branch),
            'latitude'     => -6.260000,
            'longitude'    => 106.800000,
            'accuracy'     => 10,
            'device_token' => $deviceToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);
    }

    public function test_checkout_successful(): void
    {
        $branch = $this->createBranch([
            'latitude'  => -6.175392,
            'longitude' => 106.827153,
            'radius_m'  => 100,
        ]);
        $deviceToken = 'test_token_checkout_999';
        $employee = $this->createUser([
            'branch_id'   => $branch->id,
            'device_hash' => hash('sha256', $deviceToken),
        ]);

        $shift = $this->createShift($employee, $branch, [
            'status'   => 'approved',
            'start_at' => now('Asia/Jakarta')->subHours(4)->format('Y-m-d H:i:s'),
            'end_at'   => now('Asia/Jakarta')->addHours(4)->format('Y-m-d H:i:s'),
        ]);

        // Simpan rekaman check-in sebelumnya
        $attendance = Attendance::create([
            'shift_id'   => $shift->id,
            'user_id'    => $employee->id,
            'status'     => 'present',
            'checkin_at' => now('Asia/Jakarta')->subHours(4),
        ]);

        Sanctum::actingAs($employee, ['employee']);

        $response = $this->postJson('/api/v1/attendance/checkout', [
            'shift_id'     => $shift->id,
            'latitude'     => -6.175392,
            'longitude'    => 106.827153,
            'accuracy'     => 10,
            'device_token' => $deviceToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($attendance->fresh()->checkout_at);
    }

    public function test_attendance_history_pagination(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch);
        Attendance::create([
            'shift_id'   => $shift->id,
            'user_id'    => $employee->id,
            'status'     => 'present',
            'checkin_at' => now('Asia/Jakarta'),
        ]);

        Sanctum::actingAs($employee, ['employee']);

        $response = $this->getJson('/api/v1/attendance/history');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_attendance_exception_submission(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch, [
            'status'   => 'approved',
            'start_at' => now('Asia/Jakarta')->toDateString() . ' 08:00:00',
            'end_at'   => now('Asia/Jakarta')->toDateString() . ' 17:00:00',
        ]);

        Sanctum::actingAs($employee, ['employee']);

        $response = $this->postJson("/api/v1/attendance/{$shift->id}/exception", [
            'action' => 'in',
            'reason' => 'Kamera ponsel rusak sehingga tidak dapat memindai QR code.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'in');

        $this->assertDatabaseHas('attendance_exceptions', [
            'shift_id' => $shift->id,
            'user_id'  => $employee->id,
            'action'   => 'in',
            'status'   => 'pending',
        ]);
    }

    public function test_shift_management_flow(): void
    {
        $branch = $this->createBranch();
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        Sanctum::actingAs($manager, ['manager']);

        // 1. Buat shift
        $resCreate = $this->postJson('/api/v1/shifts', [
            'user_id'    => $employee->id,
            'branch_id'  => $branch->id,
            'date'       => '2026-11-20',
            'start_time' => '08:00',
            'end_time'   => '17:00',
        ]);

        $resCreate->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $shiftId = $resCreate->json('data.id');

        // 2. Detail shift
        $this->getJson("/api/v1/shifts/{$shiftId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $shiftId);

        // 3. Update shift
        $this->patchJson("/api/v1/shifts/{$shiftId}", [
            'start_time' => '09:00',
            'end_time'   => '18:00',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        // 4. Setujui shift
        $this->postJson("/api/v1/shifts/{$shiftId}/approve")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');
    }
}

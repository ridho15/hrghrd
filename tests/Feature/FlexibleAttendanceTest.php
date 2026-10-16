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

class FlexibleAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Happy Path: Check-in karyawan flexible otomatis membuat shift dinamis dengan late_minutes = 0.
     */
    public function test_flexible_employee_checkin_creates_dynamic_shift_with_zero_late(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 14:20:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $this->assertDatabaseMissing('shifts', ['user_id' => $employee->id]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        // Shift dinamis otomatis terbuat
        $shift = Shift::where('user_id', $employee->id)->first();
        $this->assertNotNull($shift);
        $this->assertSame('approved', $shift->status);
        $this->assertSame($branch->id, $shift->branch_id);
        $this->assertSame('2026-10-15 14:20:00', $shift->start_at->toDateTimeString());

        // Presensi tercatat dengan status present dan 0 menit terlambat
        $att = Attendance::where('shift_id', $shift->id)->first();
        $this->assertNotNull($att);
        $this->assertSame('present', $att->status);
        $this->assertSame(0, $att->late_minutes);
        $this->assertSame(0, $att->late_units);

        Carbon::setTestNow();
    }

    /**
     * 2. Happy Path: Check-out karyawan flexible mengupdate shift end_at ke jam checkout aktual.
     */
    public function test_flexible_employee_checkout_updates_shift_end_at_and_calculates_actual_duration(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 14:20:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $slotIn = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $qrIn = AttendanceService::qr($branch, $slotIn);

        $this->actingAs($employee, 'sanctum');
        $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $qrIn,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ])->assertStatus(201);

        // Majukan waktu 3 jam 30 menit ke jam 17:50:00
        Carbon::setTestNow(Carbon::parse('2026-10-15 17:50:00', 'Asia/Jakarta'));

        $slotOut = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $qrOut = AttendanceService::qr($branch, $slotOut);

        $resOut = $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => $qrOut,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);

        $resOut->assertOk();

        // Shift end_at diperbarui ke jam checkout riil
        $shift = Shift::where('user_id', $employee->id)->first();
        $this->assertSame('2026-10-15 17:50:00', $shift->end_at->toDateTimeString());

        // Attendance checkout_at tercatat
        $att = Attendance::where('shift_id', $shift->id)->first();
        $this->assertNotNull($att->checkout_at);
        $this->assertSame('2026-10-15 17:50:00', Carbon::parse($att->checkout_at)->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * 3. Happy Path: Karyawan flexible dapat bertugas dan check-in di hari Minggu (weekend).
     */
    public function test_flexible_employee_can_work_on_weekend(): void
    {
        // 2026-10-18 adalah hari Minggu
        Carbon::setTestNow(Carbon::parse('2026-10-18 10:00:00', 'Asia/Jakarta'));
        $this->assertTrue(now('Asia/Jakarta')->isSunday());

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
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('shifts', [
            'user_id' => $employee->id,
            'status' => 'approved',
        ]);

        Carbon::setTestNow();
    }

    /**
     * 4. Happy Path: Presensi via Web Portal dengan shiftId = 0 untuk check-in dan check-out.
     */
    public function test_flexible_employee_web_portal_attendance_with_shift_id_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 08:30:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $slotIn = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $qrIn = AttendanceService::qr($branch, $slotIn);

        $this->actingAs($employee);

        // Check-in melalui web portal POST route('attendance.act', 0) -> /attendance/0
        $resIn = $this->withCookie('hrd_device', 'web_device_test')->post(route('attendance.act', 0), [
            'action' => 'in',
            'qr_code' => $qrIn,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
            'challenge' => '123',
        ]);

        $resIn->assertRedirect();
        $resIn->assertSessionHas('ok', 'Check-in tercatat.');

        $shift = Shift::where('user_id', $employee->id)->first();
        $this->assertNotNull($shift);

        // Majukan waktu 2 jam ke 10:30
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:30:00', 'Asia/Jakarta'));

        $slotOut = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $qrOut = AttendanceService::qr($branch, $slotOut);

        // Check-out melalui web portal POST route('attendance.act', 0)
        $resOut = $this->withCookie('hrd_device', 'web_device_test')->post(route('attendance.act', 0), [
            'action' => 'out',
            'qr_code' => $qrOut,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
            'challenge' => '123',
        ]);

        $resOut->assertRedirect();
        $resOut->assertSessionHas('ok', 'Check-out tercatat.');

        $shift->refresh();
        $this->assertSame('2026-10-15 10:30:00', $shift->end_at->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * 5. Non-Happy Path: Check-in karyawan flexible tetap diverifikasi Geofence (ditolak jika di luar radius).
     */
    public function test_flexible_employee_checkin_rejected_outside_geofence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 14:20:00', 'Asia/Jakarta'));

        $branch = $this->createBranch([
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'radius_m' => 100,
        ]);
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employee, 'sanctum');

        // Koordinat jauh di luar radius
        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => -6.185392,
            'longitude' => 106.837153,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Batas radius cabang', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * 6. Non-Happy Path: Check-in karyawan flexible ditolak jika mendeteksi Fake-GPS (mock location).
     */
    public function test_flexible_employee_checkin_rejected_on_mock_location(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 14:20:00', 'Asia/Jakarta'));

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
        $this->assertStringContainsString('Lokasi GPS terdeteksi palsu', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * 7. Non-Happy Path: Check-in ganda sebelum checkout ditolak.
     */
    public function test_flexible_employee_second_checkin_without_checkout_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 14:20:00', 'Asia/Jakarta'));

        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'schedule_type' => 'flexible',
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employee, 'sanctum');

        $res1 = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);
        $res1->assertStatus(201);

        // Check-in kedua pada shift yang sama ditolak
        $res2 = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);

        $res2->assertStatus(422);
        $this->assertStringContainsString('Check-in sudah tercatat', $res2->json('message'));

        Carbon::setTestNow();
    }

    /**
     * 8. Non-Happy Path & CRUD: Admin CRUD mengizinkan schedule_type = 'flexible'.
     */
    public function test_person_crud_supports_flexible_schedule(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $pos = $this->createPosition();

        // 1. Simpan karyawan baru dengan schedule_type = flexible
        $resStore = $this->post('/people', [
            'name' => 'Karyawan Panggilan',
            'email' => 'panggilan@example.test',
            'role' => 'employee',
            'schedule_type' => 'flexible',
            'branch_id' => $branch->id,
            'position_id' => $pos->id,
            'hired_at' => '2026-01-01',
            'base_salary' => 4500000,
            'active' => 1,
            'password' => 'Password123!',
        ]);

        $created = User::where('email', 'panggilan@example.test')->first();
        $this->assertNotNull($created);
        $resStore->assertRedirect('/people/' . $created->id);
        $this->assertSame('flexible', $created->schedule_type);
        $this->assertTrue($created->isFlexibleSchedule());
        $this->assertFalse($created->isShiftSchedule());
        $this->assertFalse($created->isOfficeSchedule());

        // 2. Update karyawan yang sudah ada menjadi flexible
        $employeeRegular = $this->createUser([
            'branch_id' => $branch->id,
            'position_id' => $pos->id,
            'schedule_type' => 'shift',
        ]);

        $resUpdate = $this->post("/people/{$employeeRegular->id}", [
            'name' => $employeeRegular->name,
            'email' => $employeeRegular->email,
            'role' => 'employee',
            'schedule_type' => 'flexible',
            'branch_id' => $branch->id,
            'position_id' => $pos->id,
            'hired_at' => '2026-01-01',
            'base_salary' => 5000000,
            'active' => 1,
        ]);

        $resUpdate->assertRedirect('/people/' . $employeeRegular->id);
        $employeeRegular->refresh();
        $this->assertSame('flexible', $employeeRegular->schedule_type);
        $this->assertTrue($employeeRegular->isFlexibleSchedule());
    }
}

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

class RoamingFlexibleAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Happy Path: Karyawan roaming jam bebas check-in dan check-out di cabang penempatan induk (Home Branch).
     */
    public function test_roaming_flexible_employee_can_checkin_and_checkout_at_home_branch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 09:15:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk Jakarta']);
        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $this->assertTrue($employee->canRoamBranches());
        $this->assertTrue($employee->isFlexibleSchedule());

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($homeBranch, $slot);

        $this->actingAs($employee, 'sanctum');

        $resIn = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $homeBranch->latitude,
            'longitude' => $homeBranch->longitude,
            'accuracy' => 10,
        ]);

        $resIn->assertStatus(201);
        $resIn->assertJsonPath('success', true);

        // Shift dinamis otomatis terbuat di cabang induk
        $shift = Shift::where('user_id', $employee->id)->first();
        $this->assertNotNull($shift);
        $this->assertSame($homeBranch->id, $shift->branch_id);
        $this->assertSame('2026-10-22 09:15:00', $shift->start_at->toDateTimeString());

        // Presensi tercatat
        $att = Attendance::where('shift_id', $shift->id)->first();
        $this->assertNotNull($att);
        $this->assertSame('present', $att->status);
        $this->assertSame(0, $att->late_minutes);

        // Majukan waktu 4 jam ke 13:15
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:15:00', 'Asia/Jakarta'));
        $slotOut = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $qrOut = AttendanceService::qr($homeBranch, $slotOut);

        $resOut = $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => $qrOut,
            'latitude' => $homeBranch->latitude,
            'longitude' => $homeBranch->longitude,
            'accuracy' => 10,
        ]);

        $resOut->assertOk();
        $shift->refresh();
        $this->assertSame('2026-10-22 13:15:00', $shift->end_at->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * 2. Happy Path: Karyawan roaming jam bebas check-in dan check-out di cabang lain yang berbeda kota/lokasi (Roaming).
     */
    public function test_roaming_flexible_employee_can_checkin_and_checkout_at_another_branch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch([
            'name' => 'Cabang Induk Jakarta',
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'radius_m' => 100,
        ]);

        $roamingBranch = $this->createBranch([
            'name' => 'Cabang Surabaya Gubeng',
            'latitude' => -7.265757,
            'longitude' => 112.752090,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $surabayaQr = AttendanceService::qr($roamingBranch, $slot);

        $this->actingAs($employee, 'sanctum');

        // Check-in di Surabaya dengan QR Surabaya dan koordinat GPS Surabaya
        $resIn = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $surabayaQr,
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 12,
        ]);

        $resIn->assertStatus(201);
        $resIn->assertJsonPath('success', true);

        // Shift dinamis otomatis terbuat di CABANG SURABAYA (bukan cabang induk)
        $shift = Shift::where('user_id', $employee->id)->first();
        $this->assertNotNull($shift);
        $this->assertSame($roamingBranch->id, $shift->branch_id);
        $this->assertSame('2026-10-22 10:00:00', $shift->start_at->toDateTimeString());

        // Presensi di Surabaya tercatat
        $att = Attendance::where('shift_id', $shift->id)->first();
        $this->assertNotNull($att);
        $this->assertSame('present', $att->status);
        $this->assertSame(0, $att->late_minutes);

        // Majukan waktu 3 jam ke 13:00 untuk check-out di Surabaya
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'Asia/Jakarta'));
        $slotOut = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $surabayaQrOut = AttendanceService::qr($roamingBranch, $slotOut);

        $resOut = $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => $surabayaQrOut,
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
        ]);

        $resOut->assertOk();
        $shift->refresh();
        $this->assertSame('2026-10-22 13:00:00', $shift->end_at->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * 3. Happy Path: Karyawan roaming bertugas di Cabang A pada hari ke-1, dan di Cabang B pada hari ke-2.
     */
    public function test_roaming_flexible_employee_can_work_at_different_branches_on_consecutive_days(): void
    {
        $branchA = $this->createBranch(['name' => 'Cabang Bandung', 'latitude' => -6.9175, 'longitude' => 107.6191]);
        $branchB = $this->createBranch(['name' => 'Cabang Yogyakarta', 'latitude' => -7.7956, 'longitude' => 110.3695]);

        $employee = $this->createUser([
            'branch_id' => $branchA->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $this->actingAs($employee, 'sanctum');

        // Hari 1 (2026-10-20): Bekerja di Cabang A
        Carbon::setTestNow(Carbon::parse('2026-10-20 08:30:00', 'Asia/Jakarta'));
        $qrA = AttendanceService::qr($branchA);
        $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $qrA,
            'latitude' => $branchA->latitude,
            'longitude' => $branchA->longitude,
            'accuracy' => 10,
        ])->assertStatus(201);

        Carbon::setTestNow(Carbon::parse('2026-10-20 16:30:00', 'Asia/Jakarta'));
        $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => AttendanceService::qr($branchA),
            'latitude' => $branchA->latitude,
            'longitude' => $branchA->longitude,
            'accuracy' => 10,
        ])->assertOk();

        // Hari 2 (2026-10-21): Bertugas keliling ke Cabang B
        Carbon::setTestNow(Carbon::parse('2026-10-21 09:00:00', 'Asia/Jakarta'));
        $qrB = AttendanceService::qr($branchB);
        $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $qrB,
            'latitude' => $branchB->latitude,
            'longitude' => $branchB->longitude,
            'accuracy' => 10,
        ])->assertStatus(201);

        Carbon::setTestNow(Carbon::parse('2026-10-21 17:00:00', 'Asia/Jakarta'));
        $this->postJson('/api/v1/attendance/checkout', [
            'qr_code' => AttendanceService::qr($branchB),
            'latitude' => $branchB->latitude,
            'longitude' => $branchB->longitude,
            'accuracy' => 10,
        ])->assertOk();

        // Verifikasi terdapat 2 shift terpisah dengan branch_id masing-masing
        $shifts = Shift::where('user_id', $employee->id)->orderBy('id')->get();
        $this->assertCount(2, $shifts);
        $this->assertSame($branchA->id, $shifts[0]->branch_id);
        $this->assertSame($branchB->id, $shifts[1]->branch_id);

        Carbon::setTestNow();
    }

    /**
     * 4. Non-Happy Path: Karyawan roaming scan QR Cabang B tetapi posisi GPS berada di luar radius Cabang B.
     */
    public function test_roaming_flexible_employee_rejected_outside_target_branch_geofence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $targetBranch = $this->createBranch([
            'name' => 'Cabang Tujuan',
            'latitude' => -7.265757,
            'longitude' => 112.752090,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($targetBranch, $slot);

        $this->actingAs($employee, 'sanctum');

        // Koordinat berjarak ~1.5 km dari cabang tujuan
        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => -7.275757,
            'longitude' => 112.752090,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Batas radius cabang', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * 5. Non-Happy Path: Karyawan reguler (allow_all_branches = false) ditolak jika check-in di cabang lain.
     */
    public function test_non_roaming_flexible_employee_rejected_at_different_branch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Cabang Induk']);
        $otherBranch = $this->createBranch(['name' => 'Cabang Lain']);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => false,
        ]);

        $this->assertFalse($employee->canRoamBranches());

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $otherQr = AttendanceService::qr($otherBranch, $slot);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $otherQr,
            'latitude' => $otherBranch->latitude,
            'longitude' => $otherBranch->longitude,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Kode cabang kedaluwarsa atau salah', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * 6. Happy Path: Presensi via Web Portal untuk karyawan roaming di cabang lain.
     */
    public function test_roaming_flexible_employee_web_portal_checkin_at_different_branch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 09:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch(['name' => 'Pusat']);
        $destBranch = $this->createBranch(['name' => 'Outlet Bekasi']);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $destQr = AttendanceService::qr($destBranch);

        $this->actingAs($employee);

        $resIn = $this->withCookie('hrd_device', 'web_roaming_test')->post(route('attendance.act', 0), [
            'action' => 'in',
            'qr_code' => $destQr,
            'latitude' => $destBranch->latitude,
            'longitude' => $destBranch->longitude,
            'accuracy' => 10,
            'challenge' => '123',
        ]);

        $resIn->assertRedirect();
        $resIn->assertSessionHas('ok', 'Check-in tercatat.');

        $shift = Shift::where('user_id', $employee->id)->first();
        $this->assertNotNull($shift);
        $this->assertSame($destBranch->id, $shift->branch_id);

        Carbon::setTestNow();
    }

    /**
     * 7. CRUD Admin: Mengatur dan memperbarui status allow_all_branches pada manajemen karyawan.
     */
    public function test_admin_can_toggle_allow_all_branches_in_people_crud(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $pos = $this->createPosition();

        // 1. Simpan karyawan baru dengan allow_all_branches = 1
        $resStore = $this->post('/people', [
            'name' => 'Karyawan Roving Multi',
            'email' => 'roving@example.test',
            'role' => 'employee',
            'schedule_type' => 'flexible',
            'branch_id' => $branch->id,
            'allow_all_branches' => 1,
            'position_id' => $pos->id,
            'hired_at' => '2026-01-01',
            'base_salary' => 4500000,
            'active' => 1,
            'password' => 'Password123!',
        ]);

        $created = User::where('email', 'roving@example.test')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->canRoamBranches());
        $this->assertTrue($created->allow_all_branches);

        // 2. Update menjadi allow_all_branches = 0
        $this->post("/people/{$created->id}", [
            'name' => $created->name,
            'email' => $created->email,
            'role' => 'employee',
            'schedule_type' => 'flexible',
            'branch_id' => $branch->id,
            'allow_all_branches' => 0,
            'position_id' => $pos->id,
            'hired_at' => '2026-01-01',
            'base_salary' => 4500000,
            'active' => 1,
        ])->assertRedirect('/people/' . $created->id);

        $created->refresh();
        $this->assertFalse($created->canRoamBranches());
        $this->assertFalse($created->allow_all_branches);
    }

    /**
     * 8. UAT Khusus: Karyawan roaming scan QR dengan format ber-prefix cabang (contoh: BDG01:12345678).
     * Memastikan sistem mengenali cabang BDG01 dan jarak dihitung terhadap cabang BDG01 (bukan cabang induk Jakarta yang berjarak 150km/3km).
     */
    public function test_roaming_flexible_employee_can_checkin_with_prefixed_qr_code(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 08:30:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch([
            'name' => 'Cabang Induk Jakarta',
            'code' => 'JKT01',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_m' => 100,
        ]);

        $roamingBranch = $this->createBranch([
            'name' => 'Cabang Bandung Pasteur',
            'code' => 'BDG01',
            'latitude' => -6.9034,
            'longitude' => 107.6186,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $token = AttendanceService::qr($roamingBranch, $slot);
        $prefixedQr = "BDG01:{$token}";

        $this->actingAs($employee, 'sanctum');

        $resIn = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $prefixedQr,
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
        ]);

        $resIn->assertStatus(201);
        $resIn->assertJsonPath('success', true);

        // Shift dinamis berhasil dibuat dan terikat ke Cabang Bandung, bukan Jakarta
        $shift = Shift::where('user_id', $employee->id)->latest('id')->first();
        $this->assertNotNull($shift);
        $this->assertSame($roamingBranch->id, $shift->branch_id);

        $att = Attendance::where('shift_id', $shift->id)->first();
        $this->assertNotNull($att);
        $this->assertSame('present', $att->status);

        Carbon::setTestNow();
    }

    /**
     * 9. UAT Khusus: Shift awal sudah terbuat di Cabang Induk (tanpa check-in).
     * Saat karyawan roaming datang ke Cabang Lain dan check-in, shift otomatis di-rebind ke Cabang Lain.
     */
    public function test_roaming_flexible_employee_rebinds_preexisting_home_shift_to_roaming_branch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 09:00:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch([
            'name' => 'Kantor Pusat Jakarta',
            'code' => 'HQ',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_m' => 100,
        ]);

        $outletBranch = $this->createBranch([
            'name' => 'Outlet Tebet (3km dari HQ)',
            'code' => 'TBT01',
            'latitude' => -6.2300,
            'longitude' => 106.8550,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        // Simulasikan ada shift otomatis/terjadwal hari ini yang terbuat di Home Branch (HQ)
        $preExistingShift = Shift::create([
            'user_id' => $employee->id,
            'branch_id' => $homeBranch->id,
            'position_id' => $employee->position_id,
            'start_at' => now('Asia/Jakarta')->startOfDay()->addHours(8),
            'end_at' => now('Asia/Jakarta')->startOfDay()->addHours(17),
            'status' => 'approved',
            'version' => 1,
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $outletToken = AttendanceService::qr($outletBranch, $slot);
        $outletQr = "TBT01:{$outletToken}";

        $this->actingAs($employee, 'sanctum');

        // Check in di Outlet Tebet dengan menyertakan shift_id dari shift yang sudah ada
        $resIn = $this->postJson('/api/v1/attendance/checkin', [
            'shift_id' => $preExistingShift->id,
            'qr_code' => $outletQr,
            'latitude' => $outletBranch->latitude,
            'longitude' => $outletBranch->longitude,
            'accuracy' => 15,
        ]);

        $resIn->assertStatus(201);
        $resIn->assertJsonPath('success', true);

        // Shift lama otomatis berpindah branch_id ke Outlet Tebet
        $preExistingShift->refresh();
        $this->assertSame($outletBranch->id, $preExistingShift->branch_id);

        // Presensi berhasil tercatat tanpa ditolak error 3km
        $att = Attendance::where('shift_id', $preExistingShift->id)->first();
        $this->assertNotNull($att);
        $this->assertSame('present', $att->status);

        Carbon::setTestNow();
    }

    /**
     * 10. UAT Khusus Web Route: Check-in via formulir web (POST /attendance/0) dengan QR ber-prefix cabang.
     */
    public function test_roaming_flexible_employee_web_route_checkin_with_prefixed_qr(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 09:30:00', 'Asia/Jakarta'));

        $homeBranch = $this->createBranch([
            'name' => 'Kantor Pusat Jakarta',
            'code' => 'HQ',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_m' => 100,
        ]);

        $roamingBranch = $this->createBranch([
            'name' => 'Cabang Yogyakarta Malioboro',
            'code' => 'YOG01',
            'latitude' => -7.7956,
            'longitude' => 110.3695,
            'radius_m' => 100,
        ]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $this->actingAs($employee);
        $challenge = 'web_chal_' . uniqid();
        session(['attendance_challenge' => $challenge]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $token = AttendanceService::qr($roamingBranch, $slot);
        $prefixedQr = "YOG01:{$token}";

        $res = $this->withCookie('hrd_device', 'web_dev_roam_test')->post('/attendance/0', [
            'action' => 'in',
            'qr_code' => $prefixedQr,
            'latitude' => $roamingBranch->latitude,
            'longitude' => $roamingBranch->longitude,
            'accuracy' => 10,
            'challenge' => $challenge,
        ]);

        $res->assertSessionHas('ok', 'Check-in tercatat.');

        $shift = Shift::where('user_id', $employee->id)->latest('id')->first();
        $this->assertNotNull($shift);
        $this->assertSame($roamingBranch->id, $shift->branch_id);

        $att = Attendance::where('shift_id', $shift->id)->first();
        $this->assertNotNull($att);
        $this->assertSame('present', $att->status);

        Carbon::setTestNow();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceAttempt;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlexibleRoamingCrossBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_flexible_roaming_employee_can_checkin_at_different_branch_on_demand(): void
    {
        $homeBranch = $this->createBranch(['code' => 'HQ', 'name' => 'Kantor Pusat', 'latitude' => -6.2088, 'longitude' => 106.8456, 'radius_m' => 100]);
        $destBranch = $this->createBranch(['code' => 'BDG', 'name' => 'Cabang Bandung', 'latitude' => -6.9175, 'longitude' => 107.6191, 'radius_m' => 100]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
            'device_hash' => hash('sha256', 'dev_token_budi'),
        ]);

        $this->actingAs($employee);
        session(['attendance_challenge' => 'chal_flexible_in']);

        // Check-in on-demand (shiftId = 0) di Cabang Bandung
        $response = $this->withCookie('hrd_device', 'dev_token_budi')->post('/attendance/0', [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($destBranch),
            'latitude' => -6.9175,
            'longitude' => 107.6191,
            'accuracy' => 10,
            'challenge' => 'chal_flexible_in',
        ]);

        $response->assertSessionHas('ok', 'Check-in tercatat.');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'status' => 'present',
        ]);

        // Pastikan shift dinamis terikat ke Cabang Bandung
        $this->assertDatabaseHas('shifts', [
            'user_id' => $employee->id,
            'branch_id' => $destBranch->id,
            'status' => 'approved',
        ]);
    }

    public function test_flexible_roaming_employee_can_checkout_at_a_different_branch_than_checkin(): void
    {
        $branchA = $this->createBranch(['code' => 'SBY', 'name' => 'Cabang Surabaya', 'latitude' => -7.2575, 'longitude' => 112.7521, 'radius_m' => 100]);
        $branchB = $this->createBranch(['code' => 'SDA', 'name' => 'Cabang Sidoarjo', 'latitude' => -7.4478, 'longitude' => 112.7183, 'radius_m' => 100]);

        $employee = $this->createUser([
            'branch_id' => $branchA->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
            'device_hash' => hash('sha256', 'dev_token_budi'),
        ]);

        $this->actingAs($employee);

        // Langkah 1: Check-in di Cabang Surabaya
        session(['attendance_challenge' => 'chal_step_1']);
        $resIn = $this->withCookie('hrd_device', 'dev_token_budi')->post('/attendance/0', [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branchA),
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'accuracy' => 10,
            'challenge' => 'chal_step_1',
        ]);
        $resIn->assertSessionHas('ok', 'Check-in tercatat.');

        $attendance = Attendance::where('user_id', $employee->id)->latest('id')->first();
        $shiftId = $attendance->shift_id;

        // Langkah 2: Sore hari berpindah dan Check-out di Cabang Sidoarjo
        session(['attendance_challenge' => 'chal_step_2']);
        $resOut = $this->withCookie('hrd_device', 'dev_token_budi')->post("/attendance/{$shiftId}", [
            'action' => 'out',
            'qr_code' => AttendanceService::qr($branchB),
            'latitude' => -7.4478,
            'longitude' => 112.7183,
            'accuracy' => 10,
            'challenge' => 'chal_step_2',
        ]);
        $resOut->assertSessionHas('ok', 'Check-out tercatat.');

        $attendance->refresh();
        $this->assertNotNull($attendance->checkout_at);
        $this->assertEquals($branchB->id, $attendance->checkout_evidence['branch_id']);
    }

    public function test_dashboard_renders_direct_checkin_and_roaming_attribute(): void
    {
        $homeBranch = $this->createBranch(['code' => 'HQ', 'name' => 'Kantor Pusat']);
        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
        ]);

        $this->actingAs($employee);
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Presensi Masuk (Jam Bebas)');
        $response->assertSee('Multi-Cabang');
        $response->assertSee('data-allow-roaming="true"', false);
    }

    public function test_unhappy_path_non_roaming_flexible_employee_rejected_at_other_branch(): void
    {
        $homeBranch = $this->createBranch(['code' => 'HQ', 'name' => 'Kantor Pusat', 'latitude' => -6.2088, 'longitude' => 106.8456, 'radius_m' => 100]);
        $otherBranch = $this->createBranch(['code' => 'MLG', 'name' => 'Cabang Malang', 'latitude' => -7.9666, 'longitude' => 112.6326, 'radius_m' => 100]);

        $employee = $this->createUser([
            'branch_id' => $homeBranch->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => false, // NON-ROAMING!
            'device_hash' => hash('sha256', 'dev_token_locked'),
        ]);

        $this->actingAs($employee);
        session(['attendance_challenge' => 'chal_fail_roam']);

        // Mencoba check-in di Cabang Malang padahal bukan roaming
        $response = $this->withCookie('hrd_device', 'dev_token_locked')->post('/attendance/0', [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($otherBranch),
            'latitude' => -7.9666,
            'longitude' => 112.6326,
            'accuracy' => 10,
            'challenge' => 'chal_fail_roam',
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        $latestAttempt = AttendanceAttempt::where('user_id', $employee->id)->latest('id')->first();
        $this->assertTrue(str_contains($latestAttempt->reason, 'Cabang') || str_contains($latestAttempt->reason, 'Kode cabang'));
    }

    public function test_unhappy_path_roaming_employee_rejected_when_outside_target_branch_geofence(): void
    {
        $branchB = $this->createBranch(['code' => 'BDG', 'name' => 'Cabang Bandung', 'latitude' => -6.9175, 'longitude' => 107.6191, 'radius_m' => 100]);

        $employee = $this->createUser([
            'branch_id' => $branchB->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
            'device_hash' => hash('sha256', 'dev_token_budi'),
        ]);

        $this->actingAs($employee);
        session(['attendance_challenge' => 'chal_gps_far']);

        // Koordinat GPS 15 km di luar Cabang Bandung
        $response = $this->withCookie('hrd_device', 'dev_token_budi')->post('/attendance/0', [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branchB),
            'latitude' => -6.8000,
            'longitude' => 107.5000,
            'accuracy' => 10,
            'challenge' => 'chal_gps_far',
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id]);

        $latestAttempt = AttendanceAttempt::where('user_id', $employee->id)->latest('id')->first();
        $this->assertStringContainsString('Posisi terdeteksi berjarak', $latestAttempt->reason);
    }

    public function test_unhappy_path_roaming_checkout_rejected_with_invalid_qr(): void
    {
        $branchA = $this->createBranch(['code' => 'HQ', 'name' => 'Kantor Pusat', 'latitude' => -6.2088, 'longitude' => 106.8456, 'radius_m' => 100]);

        $employee = $this->createUser([
            'branch_id' => $branchA->id,
            'schedule_type' => 'flexible',
            'allow_all_branches' => true,
            'device_hash' => hash('sha256', 'dev_token_budi'),
        ]);

        $this->actingAs($employee);
        session(['attendance_challenge' => 'chal_in']);

        $this->withCookie('hrd_device', 'dev_token_budi')->post('/attendance/0', [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branchA),
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'accuracy' => 10,
            'challenge' => 'chal_in',
        ]);

        $attendance = Attendance::where('user_id', $employee->id)->latest('id')->first();

        // Check-out dengan kode QR palsu
        session(['attendance_challenge' => 'chal_out_bad']);
        $resOut = $this->withCookie('hrd_device', 'dev_token_budi')->post("/attendance/{$attendance->shift_id}", [
            'action' => 'out',
            'qr_code' => '99999999',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'accuracy' => 10,
            'challenge' => 'chal_out_bad',
        ]);

        $resOut->assertSessionHasErrors('attendance');
        $attendance->refresh();
        $this->assertNull($attendance->checkout_at);
    }
}

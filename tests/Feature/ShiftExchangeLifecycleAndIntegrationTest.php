<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\ShiftExchange;
use App\Services\AttendanceService;
use App\Services\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftExchangeLifecycleAndIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_replacement_lifecycle_transfers_shift_ownership_and_logs_audit(): void
    {
        $branch = $this->createBranch();
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan A']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan B']);

        $shift = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-25 08:00:00',
            'end_at' => '2026-10-25 16:00:00',
            'version' => 1,
        ]);

        // 1. Karyawan dilarang mengajukan pertukaran mandiri (403)
        $this->actingAs($empA);
        $resEmp = $this->post("/shifts/{$shift->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Perlu menghadiri wisuda keluarga',
        ]);
        $resEmp->assertForbidden();

        // 1b. Manajer mengajukan permohonan penggantian terpusat
        $this->actingAs($manager);
        $resReq = $this->post("/shifts/{$shift->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Perlu menghadiri wisuda keluarga',
        ]);
        $resReq->assertRedirect(route('shifts.exchanges'));

        $exchange = ShiftExchange::where('requester_shift_id', $shift->id)->first();
        $this->assertNotNull($exchange);
        $this->assertSame('pending_peer', $exchange->status);
        $this->assertSame($empA->id, $shift->fresh()->user_id);

        // 2. Karyawan B menyetujui (Peer Accept)
        $this->actingAs($empB);
        $resPeer = $this->post("/shifts/exchanges/{$exchange->id}/peer-respond", [
            'action' => 'accept',
            'note' => 'Siap menggantikan jadwal',
        ]);
        $resPeer->assertRedirect();
        $this->assertSame('pending_manager', $exchange->fresh()->status);
        $this->assertSame('Siap menggantikan jadwal', $exchange->fresh()->replacement_note);

        // 3. Manajer menyetujui (Manager Approval)
        $this->actingAs($manager);
        $resMgr = $this->post("/shifts/exchanges/{$exchange->id}/review", [
            'decision' => 'approve',
            'note' => 'Disetujui manajerial',
        ]);
        $resMgr->assertRedirect();

        // Verifikasi kepemilikan shift resmi berpindah ke Karyawan B
        $this->assertSame('approved', $exchange->fresh()->status);
        $this->assertSame($empB->id, $shift->fresh()->user_id);
        $this->assertSame(2, $shift->fresh()->version);

        // Verifikasi Jejak Audit
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'shift',
            'subject_id' => $shift->id,
            'action' => 'reassigned',
        ]);
    }

    public function test_full_mutual_swap_lifecycle_swaps_both_shifts_atomically(): void
    {
        $branch = $this->createBranch();
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan A']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan B']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-20 08:00:00',
            'end_at' => '2026-10-20 16:00:00',
            'version' => 1,
        ]);

        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => '2026-10-22 08:00:00',
            'end_at' => '2026-10-22 16:00:00',
            'version' => 1,
        ]);

        // 1. Karyawan dilarang mengajukan swap mandiri (403)
        $this->actingAs($empA);
        $resEmp = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'swap',
            'target_shift_id' => $shiftB->id,
            'reason' => 'Tukar shift hari Selasa dengan Kamis',
        ]);
        $resEmp->assertForbidden();

        // 1b. Manajer memproses swap dua arah
        $this->actingAs($manager);
        $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'swap',
            'target_shift_id' => $shiftB->id,
            'reason' => 'Tukar shift hari Selasa dengan Kamis',
        ]);

        $exchange = ShiftExchange::where('requester_shift_id', $shiftA->id)->first();
        $this->assertNotNull($exchange);
        $this->assertSame('swap', $exchange->type);

        // 2. Karyawan B menyetujui
        $this->actingAs($empB);
        $this->post("/shifts/exchanges/{$exchange->id}/peer-respond", [
            'action' => 'accept',
        ]);
        $this->assertSame('pending_manager', $exchange->fresh()->status);

        // 3. Manajer menyetujui
        $this->actingAs($manager);
        $this->post("/shifts/exchanges/{$exchange->id}/review", [
            'decision' => 'approve',
        ]);

        // Verifikasi kedua shift bertukar kepemilikan secara atomik
        $this->assertSame($empB->id, $shiftA->fresh()->user_id);
        $this->assertSame($empA->id, $shiftB->fresh()->user_id);
        $this->assertSame(2, $shiftA->fresh()->version);
        $this->assertSame(2, $shiftB->fresh()->version);
    }

    public function test_direct_manager_reassignment_bypasses_peer_step_and_transfers_immediately(): void
    {
        $branch = $this->createBranch();
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-26 08:00:00',
            'end_at' => '2026-10-26 16:00:00',
        ]);

        $this->actingAs($manager);
        $response = $this->post("/shifts/{$shift->id}/reassign", [
            'replacement_id' => $empB->id,
            'reason' => 'Penugasan darurat oleh supervisor cabang',
        ]);

        $response->assertRedirect();
        $this->assertSame($empB->id, $shift->fresh()->user_id);

        $exchange = ShiftExchange::where('requester_shift_id', $shift->id)->first();
        $this->assertNotNull($exchange);
        $this->assertSame('approved', $exchange->status);
        $this->assertSame($manager->id, $exchange->reviewed_by);
    }

    public function test_peer_rejection_marks_exchange_rejected_without_modifying_shift(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($empA, $branch, ['start_at' => '2026-10-27 08:00:00', 'end_at' => '2026-10-27 16:00:00']);

        $exchange = ShiftExchange::create([
            'type' => 'replacement',
            'branch_id' => $branch->id,
            'requester_id' => $empA->id,
            'requester_shift_id' => $shift->id,
            'replacement_id' => $empB->id,
            'reason' => 'Keperluan keluarga',
            'status' => 'pending_peer',
        ]);

        $this->actingAs($empB);
        $response = $this->post("/shifts/exchanges/{$exchange->id}/peer-respond", [
            'action' => 'reject',
            'note' => 'Maaf saya ada agenda lain',
        ]);

        $response->assertRedirect();
        $this->assertSame('rejected', $exchange->fresh()->status);
        $this->assertSame($empA->id, $shift->fresh()->user_id);
    }

    public function test_manager_rejection_marks_exchange_rejected_without_modifying_shift(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($empA, $branch, ['start_at' => '2026-10-27 08:00:00', 'end_at' => '2026-10-27 16:00:00']);

        $exchange = ShiftExchange::create([
            'type' => 'replacement',
            'branch_id' => $branch->id,
            'requester_id' => $empA->id,
            'requester_shift_id' => $shift->id,
            'replacement_id' => $empB->id,
            'reason' => 'Keperluan keluarga',
            'status' => 'pending_manager',
            'peer_responded_at' => now(),
        ]);

        $this->actingAs($manager);
        $response = $this->post("/shifts/exchanges/{$exchange->id}/review", [
            'decision' => 'reject',
            'note' => 'Tidak disetujui karena kekurangan staf di cabang',
        ]);

        $response->assertRedirect();
        $this->assertSame('rejected', $exchange->fresh()->status);
        $this->assertSame($empA->id, $shift->fresh()->user_id);
    }

    public function test_attendance_and_payroll_integration_after_shift_replacement(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $empA = $this->createUser(['branch_id' => $branch->id, 'base_salary' => 5000000, 'device_hash' => hash('sha256', 'dev_token_a')]);
        $empB = $this->createUser(['branch_id' => $branch->id, 'base_salary' => 5000000, 'device_hash' => hash('sha256', 'dev_token_b')]);

        // Shift dibuat untuk A pada hari ini
        $now = now('Asia/Jakarta');
        $shift = $this->createShift($empA, $branch, [
            'status' => 'approved',
            'start_at' => $now->copy()->subMinutes(10)->format('Y-m-d H:i:s'),
            'end_at' => $now->copy()->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        // Manajer mengalihkan shift ke B
        $this->actingAs($manager);
        $this->post("/shifts/{$shift->id}/reassign", [
            'replacement_id' => $empB->id,
            'reason' => 'Penggantian staf resmi',
        ]);
        $this->assertSame($empB->id, $shift->fresh()->user_id);

        // Karyawan A mencoba absen pada shift ini -> Tertolak karena shift sudah milik B!
        $this->actingAs($empA);
        session(['attendance_challenge' => 'challenge_a']);
        $resA = $this->withCookie('hrd_device', 'dev_token_a')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => 'challenge_a',
        ]);
        $resA->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendances', ['shift_id' => $shift->id]);

        // Karyawan B melakukan absen pada shift ini -> Diterima dan sukses!
        $this->actingAs($empB);
        session(['attendance_challenge' => 'challenge_b']);
        $resB = $this->withCookie('hrd_device', 'dev_token_b')->post("/attendance/{$shift->id}", [
            'action' => 'in',
            'qr_code' => AttendanceService::qr($branch),
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'challenge' => 'challenge_b',
        ]);
        $resB->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'shift_id' => $shift->id,
            'user_id' => $empB->id,
            'status' => 'present',
        ]);

        // Verifikasi kalkulasi payroll:
        // Bulan ini: B memiliki shift dan hadir, A tidak memiliki shift ini (sehingga tidak kena penalti missing)
        $calculator = new PayrollCalculator();
        $currentMonth = $now->format('Y-m');

        $payrollB = $calculator->calculate($empB, $currentMonth);
        $payrollA = $calculator->calculate($empA, $currentMonth);

        // A tidak terkena potongan kehadiran untuk shift tersebut karena shift tersebut sudah bukan miliknya
        $this->assertEmpty($payrollA['unpaid_dates']);
    }
}

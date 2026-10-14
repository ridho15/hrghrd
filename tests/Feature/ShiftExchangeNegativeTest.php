<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceException;
use App\Models\PayrollRun;
use App\Models\ShiftExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftExchangeNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_request_exchange_if_replacement_has_overlapping_shift(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);

        $this->actingAs($empA);

        // Shift A: 09:00 - 17:00
        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-15 09:00:00',
            'end_at' => '2026-10-15 17:00:00',
        ]);

        // Shift B: 12:00 - 20:00 (tumpang tindih dengan Shift A)
        $this->createShift($empB, $branch, [
            'start_at' => '2026-10-15 12:00:00',
            'end_at' => '2026-10-15 20:00:00',
        ]);

        // Karyawan dilarang submit pertukaran mandiri (403)
        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Saya berhalangan hadir urusan keluarga',
        ]);
        $response->assertStatus(403);

        // Manajer yang memproses ditolak karena jadwal bentrok (409)
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $resMgr = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Saya berhalangan hadir urusan keluarga',
        ]);
        $resMgr->assertStatus(409);
        $this->assertDatabaseMissing('shift_exchanges', [
            'requester_shift_id' => $shiftA->id,
            'replacement_id' => $empB->id,
        ]);
    }

    public function test_cannot_request_mutual_swap_if_requester_overlaps_target_shift(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);

        $this->actingAs($empA);

        // Shift A yang ingin ditukar: 15 Okt 08:00 - 16:00
        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at' => '2026-10-15 16:00:00',
        ]);

        // Shift target milik B: 16 Okt 08:00 - 16:00
        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => '2026-10-16 08:00:00',
            'end_at' => '2026-10-16 16:00:00',
        ]);

        // Namun A sudah punya shift LAIN di 16 Okt 10:00 - 18:00 (bentrok dengan target shift)
        $this->createShift($empA, $branch, [
            'start_at' => '2026-10-16 10:00:00',
            'end_at' => '2026-10-16 18:00:00',
        ]);

        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'swap',
            'target_shift_id' => $shiftB->id,
            'reason' => 'Ingin bertukar hari kerja',
        ]);
        $response->assertStatus(403);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $resMgr = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'swap',
            'target_shift_id' => $shiftB->id,
            'reason' => 'Ingin bertukar hari kerja',
        ]);
        $resMgr->assertStatus(409);
    }

    public function test_cannot_request_exchange_on_shift_that_already_has_attendance(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);

        $this->actingAs($empA);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at' => '2026-10-15 16:00:00',
        ]);

        Attendance::create([
            'shift_id' => $shiftA->id,
            'user_id' => $empA->id,
            'checkin_at' => '2026-10-15 07:50:00',
            'status' => 'present',
        ]);

        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Pergantian jadwal yang sudah check-in',
        ]);
        $response->assertStatus(403);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $resMgr = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Pergantian jadwal yang sudah check-in',
        ]);
        $resMgr->assertStatus(409);
    }

    public function test_cannot_request_exchange_on_shift_with_active_exception_ticket(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);

        $this->actingAs($empA);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at' => '2026-10-15 16:00:00',
        ]);

        AttendanceException::create([
            'shift_id' => $shiftA->id,
            'user_id' => $empA->id,
            'action' => 'checkin',
            'status' => 'pending',
            'reason' => 'Kendala ban bocor di jalan tol',
        ]);

        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Mencoba ganti shift dengan tiket kendala aktif',
        ]);
        $response->assertStatus(403);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $resMgr = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Mencoba ganti shift dengan tiket kendala aktif',
        ]);
        $resMgr->assertStatus(409);
    }

    public function test_cannot_request_exchange_in_locked_payroll_month(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);

        $this->actingAs($empA);

        // Payroll Agustus 2026 sudah dikunci
        PayrollRun::create([
            'branch_id' => $branch->id,
            'month' => '2026-08',
            'status' => 'locked',
        ]);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-08-10 08:00:00',
            'end_at' => '2026-08-10 16:00:00',
        ]);

        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Pergantian di bulan yang payroll-nya terkunci',
        ]);
        $response->assertStatus(403);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $resMgr = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Pergantian di bulan yang payroll-nya terkunci',
        ]);
        $resMgr->assertStatus(409);
    }

    public function test_cannot_select_inactive_employee_as_replacement(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id, 'active' => false]);

        $this->actingAs($empA);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-18 08:00:00',
            'end_at' => '2026-10-18 16:00:00',
        ]);

        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Mencoba memilih rekan yang sudah non-aktif',
        ]);
        $response->assertStatus(403);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $resMgr = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Mencoba memilih rekan yang sudah non-aktif',
        ]);
        $resMgr->assertStatus(422);
    }

    public function test_cannot_request_exchange_with_employee_from_different_branch_without_admin(): void
    {
        $branchA = $this->createBranch();
        $branchB = $this->createBranch();

        $empA = $this->createUser(['branch_id' => $branchA->id]);
        $empB = $this->createUser(['branch_id' => $branchB->id]);

        $this->actingAs($empA);

        $shiftA = $this->createShift($empA, $branchA, [
            'start_at' => '2026-10-18 08:00:00',
            'end_at' => '2026-10-18 16:00:00',
        ]);

        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Pertukaran lintas cabang tidak sah',
        ]);
        $response->assertStatus(403);

        $managerA = $this->createUser(['role' => 'manager', 'branch_id' => $branchA->id]);
        $this->actingAs($managerA);
        $resMgr = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Pertukaran lintas cabang tidak sah',
        ]);
        $resMgr->assertStatus(422);
    }

    public function test_cannot_exchange_shift_with_self(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);

        $this->actingAs($empA);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-18 08:00:00',
            'end_at' => '2026-10-18 16:00:00',
        ]);

        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empA->id,
            'reason' => 'Mencoba menukar dengan diri sendiri',
        ]);
        $response->assertStatus(403);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $resMgr = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empA->id,
            'reason' => 'Mencoba menukar dengan diri sendiri',
        ]);
        $resMgr->assertStatus(422);
    }

    public function test_unauthorized_peer_cannot_respond_to_exchange_request(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);
        $empC = $this->createUser(['branch_id' => $branch->id]);

        $shiftA = $this->createShift($empA, $branch, ['start_at' => '2026-10-22 08:00:00', 'end_at' => '2026-10-22 16:00:00']);

        $exchange = ShiftExchange::create([
            'type' => 'replacement',
            'branch_id' => $branch->id,
            'requester_id' => $empA->id,
            'requester_shift_id' => $shiftA->id,
            'replacement_id' => $empB->id,
            'reason' => 'Izin keperluan keluarga',
            'status' => 'pending_peer',
        ]);

        // Karyawan C (pihak ketiga) mencoba menerima permohonan milik B
        $this->actingAs($empC);
        $response = $this->post("/shifts/exchanges/{$exchange->id}/peer-respond", [
            'action' => 'accept',
        ]);

        $response->assertStatus(403);
        $this->assertSame('pending_peer', $exchange->fresh()->status);
    }

    public function test_regular_employee_cannot_perform_manager_review(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);

        $shiftA = $this->createShift($empA, $branch, ['start_at' => '2026-10-22 08:00:00', 'end_at' => '2026-10-22 16:00:00']);

        $exchange = ShiftExchange::create([
            'type' => 'replacement',
            'branch_id' => $branch->id,
            'requester_id' => $empA->id,
            'requester_shift_id' => $shiftA->id,
            'replacement_id' => $empB->id,
            'reason' => 'Izin keperluan keluarga',
            'status' => 'pending_manager',
            'peer_responded_at' => now(),
        ]);

        // Karyawan biasa mencoba approve review
        $this->actingAs($empB);
        $response = $this->post("/shifts/exchanges/{$exchange->id}/review", [
            'decision' => 'approve',
        ]);

        $response->assertStatus(403);
        $this->assertSame('pending_manager', $exchange->fresh()->status);
    }

    public function test_manager_of_other_branch_cannot_review_exchange(): void
    {
        $branchA = $this->createBranch();
        $branchB = $this->createBranch();

        $empA = $this->createUser(['branch_id' => $branchA->id]);
        $empB = $this->createUser(['branch_id' => $branchA->id]);
        $managerB = $this->createUser(['role' => 'manager', 'branch_id' => $branchB->id]);

        $shiftA = $this->createShift($empA, $branchA, ['start_at' => '2026-10-22 08:00:00', 'end_at' => '2026-10-22 16:00:00']);

        $exchange = ShiftExchange::create([
            'type' => 'replacement',
            'branch_id' => $branchA->id,
            'requester_id' => $empA->id,
            'requester_shift_id' => $shiftA->id,
            'replacement_id' => $empB->id,
            'reason' => 'Izin keperluan keluarga',
            'status' => 'pending_manager',
            'peer_responded_at' => now(),
        ]);

        // Manajer Cabang B mencoba approve cabang A
        $this->actingAs($managerB);
        $response = $this->post("/shifts/exchanges/{$exchange->id}/review", [
            'decision' => 'approve',
        ]);

        $response->assertStatus(403);
        $this->assertSame('pending_manager', $exchange->fresh()->status);
    }

    public function test_cannot_respond_or_review_already_resolved_exchange(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);

        $shiftA = $this->createShift($empA, $branch, ['start_at' => '2026-10-22 08:00:00', 'end_at' => '2026-10-22 16:00:00']);

        $exchange = ShiftExchange::create([
            'type' => 'replacement',
            'branch_id' => $branch->id,
            'requester_id' => $empA->id,
            'requester_shift_id' => $shiftA->id,
            'replacement_id' => $empB->id,
            'reason' => 'Izin keperluan keluarga',
            'status' => 'rejected', // Sudah ditolak sebelumnya
            'reviewed_by' => $manager->id,
            'reviewed_at' => now(),
        ]);

        // Coba review ulang permohonan yang sudah rejected
        $response = $this->post("/shifts/exchanges/{$exchange->id}/review", [
            'decision' => 'approve',
        ]);

        $response->assertStatus(409);
        $this->assertSame('rejected', $exchange->fresh()->status);
    }

    public function test_employee_cannot_exchange_shift_they_do_not_own(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);
        $empC = $this->createUser(['branch_id' => $branch->id]);

        // Shift milik A
        $shiftA = $this->createShift($empA, $branch, ['start_at' => '2026-10-25 08:00:00', 'end_at' => '2026-10-25 16:00:00']);

        // Karyawan B login dan mencoba menukarkan shift milik A
        $this->actingAs($empB);
        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empC->id,
            'reason' => 'Mencoba menukarkan shift milik orang lain',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_create_duplicate_active_exchange_on_same_shift(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id]);
        $empB = $this->createUser(['branch_id' => $branch->id]);
        $empC = $this->createUser(['branch_id' => $branch->id]);

        $this->actingAs($empA);
        $shiftA = $this->createShift($empA, $branch, ['start_at' => '2026-10-25 08:00:00', 'end_at' => '2026-10-25 16:00:00']);

        // Request 1 berhasil dibuat
        ShiftExchange::create([
            'type' => 'replacement',
            'branch_id' => $branch->id,
            'requester_id' => $empA->id,
            'requester_shift_id' => $shiftA->id,
            'replacement_id' => $empB->id,
            'reason' => 'Pengajuan pertama yang masih pending',
            'status' => 'pending_peer',
        ]);

        // Request 2 untuk shift yang sama: karyawan dilarang (403)
        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empC->id,
            'reason' => 'Pengajuan kedua sebelum pengajuan pertama selesai',
        ]);
        $response->assertStatus(403);

        // Manajer yang memproses ditolak karena ada pengajuan aktif (409)
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $resMgr = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type' => 'replacement',
            'replacement_id' => $empC->id,
            'reason' => 'Pengajuan kedua sebelum pengajuan pertama selesai',
        ]);
        $resMgr->assertStatus(409);
    }
}

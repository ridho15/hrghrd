<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\ShiftExchange;
use App\Services\ShiftExchangeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftExchangeSelfCancelAndAutoExpireTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_self_cancel_exchange_when_status_is_pending_peer(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan A', 'active' => true]);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan B', 'active' => true]);

        $shift = $this->createShift($empA, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0),
            'version'  => 1,
        ]);

        $exchange = ShiftExchange::create([
            'type'               => 'replacement',
            'branch_id'          => $branch->id,
            'requester_id'       => $empA->id,
            'requester_shift_id' => $shift->id,
            'replacement_id'     => $empB->id,
            'reason'             => 'Salah input rekan kerja',
            'status'             => 'pending_peer',
        ]);

        // Aktor Pemohon (Karyawan A) membatalkan via API endpoint
        $this->actingAs($empA);
        $res = $this->postJson("/api/v1/shifts/exchanges/{$exchange->id}/cancel", [
            'reason' => 'Saya membatalkan karena rencana acara ditunda.',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Permohonan pertukaran shift berhasil dibatalkan.',
            ]);

        $exchange->refresh();
        $this->assertSame('cancelled', $exchange->status);
        $this->assertStringContainsString('Dibatalkan oleh pemohon', $exchange->review_note);
        $this->assertStringContainsString('rencana acara ditunda', $exchange->review_note);
        $this->assertNotNull($exchange->reviewed_at);

        // Pastikan shift milik A tetap aman
        $this->assertSame($empA->id, $shift->fresh()->user_id);

        // Pastikan Audit Log tercatat (kolom subject_type & subject_id)
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'shift_exchange',
            'subject_id'   => $exchange->id,
            'action'       => 'requester_cancel',
        ]);
    }

    public function test_self_cancel_is_forbidden_for_other_users(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan A', 'active' => true]);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan B', 'active' => true]);
        $empC = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan C', 'active' => true]);

        $shift = $this->createShift($empA, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0),
        ]);

        $exchange = ShiftExchange::create([
            'type'               => 'replacement',
            'branch_id'          => $branch->id,
            'requester_id'       => $empA->id,
            'requester_shift_id' => $shift->id,
            'replacement_id'     => $empB->id,
            'reason'             => 'Alasan pemohon',
            'status'             => 'pending_peer',
        ]);

        // Rekan B maupun pihak ketiga C tidak boleh membatalkan tiket milik A
        $this->actingAs($empB);
        $resB = $this->postJson("/api/v1/shifts/exchanges/{$exchange->id}/cancel", []);
        $resB->assertStatus(403);

        $this->actingAs($empC);
        $resC = $this->postJson("/api/v1/shifts/exchanges/{$exchange->id}/cancel", []);
        $resC->assertStatus(403);

        $this->assertSame('pending_peer', $exchange->fresh()->status);
    }

    public function test_self_cancel_rejected_if_exchange_not_pending_peer(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan A', 'active' => true]);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan B', 'active' => true]);

        $shift = $this->createShift($empA, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0),
        ]);

        // Status sudah pending_manager (rekan B sudah setuju)
        $exchange = ShiftExchange::create([
            'type'               => 'replacement',
            'branch_id'          => $branch->id,
            'requester_id'       => $empA->id,
            'requester_shift_id' => $shift->id,
            'replacement_id'     => $empB->id,
            'reason'             => 'Sudah disetujui rekan',
            'status'             => 'pending_manager',
        ]);

        $this->actingAs($empA);
        $res = $this->postJson("/api/v1/shifts/exchanges/{$exchange->id}/cancel", []);

        // Harus ditolak karena rekan sudah setuju dan proses ada di ranah HRD
        $res->assertStatus(422);
        $this->assertSame('pending_manager', $exchange->fresh()->status);
    }

    public function test_auto_expire_cancels_pending_peer_requests_within_six_hours_before_shift(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan A', 'active' => true]);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan B', 'active' => true]);

        // Shift 1: Dimulai 4 jam lagi (< 6 jam, harus expired)
        $shiftNear = $this->createShift($empA, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addHours(4),
            'end_at'   => Carbon::now('Asia/Jakarta')->addHours(12),
        ]);
        $exchangeNear = ShiftExchange::create([
            'type'               => 'replacement',
            'branch_id'          => $branch->id,
            'requester_id'       => $empA->id,
            'requester_shift_id' => $shiftNear->id,
            'replacement_id'     => $empB->id,
            'reason'             => 'Tukar mendadak',
            'status'             => 'pending_peer',
        ]);

        // Shift 2: Dimulai 24 jam lagi (> 6 jam, harus tetap pending_peer)
        $shiftFar = $this->createShift($empA, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addHours(24),
            'end_at'   => Carbon::now('Asia/Jakarta')->addHours(32),
        ]);
        $exchangeFar = ShiftExchange::create([
            'type'               => 'replacement',
            'branch_id'          => $branch->id,
            'requester_id'       => $empA->id,
            'requester_shift_id' => $shiftFar->id,
            'replacement_id'     => $empB->id,
            'reason'             => 'Tukar minggu depan',
            'status'             => 'pending_peer',
        ]);

        // Panggil service expireOutdatedPeerRequests
        $service = app(ShiftExchangeService::class);
        $expiredCount = $service->expireOutdatedPeerRequests();

        $this->assertSame(1, $expiredCount);
        $this->assertSame('cancelled', $exchangeNear->fresh()->status);
        $this->assertStringContainsString('Otomatis dibatalkan sistem', $exchangeNear->fresh()->review_note);
        $this->assertStringContainsString('H-6 jam', $exchangeNear->fresh()->review_note);

        // exchangeFar tidak boleh terpengaruh
        $this->assertSame('pending_peer', $exchangeFar->fresh()->status);

        // Pastikan audit log tercatat
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'shift_exchange',
            'subject_id'   => $exchangeNear->id,
            'action'       => 'auto_expire',
        ]);
    }

    public function test_api_exchanges_endpoint_automatically_triggers_auto_expire(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan A', 'active' => true]);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan B', 'active' => true]);

        // Shift dimulai 2 jam lagi
        $shift = $this->createShift($empA, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addHours(2),
            'end_at'   => Carbon::now('Asia/Jakarta')->addHours(10),
        ]);
        $exchange = ShiftExchange::create([
            'type'               => 'replacement',
            'branch_id'          => $branch->id,
            'requester_id'       => $empA->id,
            'requester_shift_id' => $shift->id,
            'replacement_id'     => $empB->id,
            'reason'             => 'Tukar mepet jam kerja',
            'status'             => 'pending_peer',
        ]);

        $this->actingAs($empA);
        $res = $this->getJson('/api/v1/shifts/exchanges');
        $res->assertStatus(200);

        // Setelah dipanggil endpoint exchanges, status exchange harus sudah cancelled
        $this->assertSame('cancelled', $exchange->fresh()->status);
    }
}

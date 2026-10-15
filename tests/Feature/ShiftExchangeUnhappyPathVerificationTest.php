<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ShiftExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftExchangeUnhappyPathVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SKENARIO 1 (UNHAPPY PATH): Overlap Conflict Prevention.
     * Sistem memblokir pengajuan pertukaran jika rekan pengganti memiliki jadwal yang bentrok (409 Conflict).
     */
    public function test_scenario_1_overlap_conflict_prevents_exchange_submission(): void
    {
        $branch = $this->createBranch(['name' => 'Cabang Jakarta']);
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Pemohon']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Siti Pengganti']);

        // Shift Pemohon: 21 Okt 08:00 - 16:00
        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-21 08:00:00',
            'end_at'   => '2026-10-21 16:00:00',
        ]);

        // Shift Rekan Pengganti: 21 Okt 12:00 - 20:00 (Tumpang tindih 4 jam dengan Shift A)
        $this->createShift($empB, $branch, [
            'start_at' => '2026-10-21 12:00:00',
            'end_at'   => '2026-10-21 20:00:00',
        ]);

        $this->actingAs($empA);

        // Ajukan pengalihan searah ke rekan yang bentrok
        $response = $this->post("/shifts/{$shiftA->id}/exchange", [
            'type'           => 'replacement',
            'replacement_id' => $empB->id,
            'reason'         => 'Permohonan uji jadwal bentrok [TEST-OVERLAP]',
        ]);

        // Ekspektasi: 409 Conflict
        $response->assertStatus(409);

        // Database tidak boleh menyimpan transaksi yang gagal
        $this->assertDatabaseMissing('shift_exchanges', [
            'requester_shift_id' => $shiftA->id,
            'replacement_id'     => $empB->id,
        ]);
    }

    /**
     * SKENARIO 2 (UNHAPPY PATH): Attendance Lock.
     * Shift yang sudah memiliki rekaman absensi terkunci dari pengajuan (UI tidak menampilkan tombol, direct POST melempar 409).
     */
    public function test_scenario_2_attendance_lock_hides_exchange_action_and_rejects_direct_post(): void
    {
        $branch = $this->createBranch();
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Hadir']);
        $peer = $this->createUser(['branch_id' => $branch->id, 'name' => 'Rekan Kerja']);

        // Shift 19 Okt 08:00 - 16:00
        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-19 08:00:00',
            'end_at'   => '2026-10-19 16:00:00',
            'status'   => 'approved',
        ]);

        // Buat rekaman absensi hadir
        Attendance::create([
            'shift_id'    => $shift->id,
            'user_id'     => $emp->id,
            'checkin_at'  => '2026-10-19 07:55:00',
            'checkout_at' => '2026-10-19 16:05:00',
            'status'      => 'present',
        ]);

        $this->actingAs($emp);

        // 1. Verifikasi UI Kalender: Modal detail ada, namun tombol dan modal pengajuan terkunci
        $calResponse = $this->get('/shifts/calendar?month=2026-10');
        $calResponse->assertOk();
        $calResponse->assertSee("modal-shift-{$shift->id}");
        $calResponse->assertSee('Hadir');

        // Tombol Ajukan Tukar / Ganti Shift TIDAK BOLEH tampil di modal shift yang berabsensi
        $calResponse->assertDontSee("data-open-modal=\"modal-exchange-shift-{$shift->id}\"", false);
        // Modal form pengajuan TIDAK BOLEH dirender
        $calResponse->assertDontSee("id=\"modal-exchange-shift-{$shift->id}\"", false);

        // 2. Verifikasi Direct POST bypass: Backend tetap menolak dengan HTTP 409
        $postResponse = $this->post("/shifts/{$shift->id}/exchange", [
            'type'           => 'replacement',
            'replacement_id' => $peer->id,
            'reason'         => 'Mencoba bypass shift yang sudah berabsensi',
        ]);

        $postResponse->assertStatus(409);
        $this->assertDatabaseMissing('shift_exchanges', [
            'requester_shift_id' => $shift->id,
        ]);
    }

    /**
     * SKENARIO 3 (UNHAPPY PATH): Strict Gate Management Approval.
     * Manajer atau Admin dilarang menyetujui pengajuan sebelum rekan pengganti menyetujui (UI mengunci tombol, POST melempar 422).
     */
    public function test_scenario_3_strict_gate_prevents_manager_approval_during_pending_peer(): void
    {
        $branch = $this->createBranch();
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id, 'name' => 'Manajer Cabang']);
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan A']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan B']);

        $shift = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-22 08:00:00',
            'end_at'   => '2026-10-22 16:00:00',
        ]);

        // Permohonan dibuat dengan status pending_peer
        $exchange = ShiftExchange::create([
            'type'               => 'replacement',
            'branch_id'          => $branch->id,
            'requester_id'       => $empA->id,
            'requester_shift_id' => $shift->id,
            'replacement_id'     => $empB->id,
            'reason'             => 'Menunggu persetujuan rekan [TEST-STRICT-GATE]',
            'status'             => 'pending_peer',
        ]);

        $this->actingAs($manager);

        // 1. Verifikasi UI Manajemen (/shifts/exchanges): Tampil badge "Menunggu Konfirmasi Rekan", TIDAK ada tombol Setujui
        $viewResponse = $this->get('/shifts/exchanges');
        $viewResponse->assertOk();
        $viewResponse->assertSee('Menunggu Konfirmasi Rekan');

        // Form action review manajemen untuk ID ini TIDAK BOLEH tampil saat masih pending_peer
        $viewResponse->assertDontSee(route('shifts.exchanges.review', $exchange->id), false);

        // 2. Verifikasi Direct POST bypass: Manajer mencoba bypass via HTTP request
        $bypassResponse = $this->post("/shifts/exchanges/{$exchange->id}/review", [
            'decision' => 'approve',
            'note'     => 'Coba approve sebelum rekan setuju',
        ]);

        // Ekspektasi: 422 Unprocessable Content (Strict Gate)
        $bypassResponse->assertStatus(422);

        // Status permohonan tetap pending_peer
        $this->assertSame('pending_peer', $exchange->fresh()->status);
        // Shift tetap milik Karyawan A
        $this->assertSame($empA->id, $shift->fresh()->user_id);
    }

    /**
     * SKENARIO 4 (UNHAPPY PATH): Peer Rejection.
     * Rekan pengganti menolak permohonan: status permohonan langsung resmi rejected dan tidak diteruskan ke manajer.
     */
    public function test_scenario_4_peer_rejection_marks_request_rejected_and_aborts_escalation(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Pemohon']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Siti Pengganti']);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id, 'name' => 'Manajer']);

        $shift = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-23 08:00:00',
            'end_at'   => '2026-10-23 16:00:00',
        ]);

        $exchange = ShiftExchange::create([
            'type'               => 'replacement',
            'branch_id'          => $branch->id,
            'requester_id'       => $empA->id,
            'requester_shift_id' => $shift->id,
            'replacement_id'     => $empB->id,
            'reason'             => 'Permohonan yang akan ditolak rekan [TEST-PEER-REJECT]',
            'status'             => 'pending_peer',
        ]);

        // Rekan login
        $this->actingAs($empB);

        // Verifikasi tombol respon rekan muncul di halaman /shifts/exchanges
        $viewResponse = $this->get('/shifts/exchanges');
        $viewResponse->assertOk();
        $viewResponse->assertSee(route('shifts.exchanges.peer-respond', $exchange->id));

        // Rekan menolak permohonan (Reject)
        $rejectResponse = $this->post("/shifts/exchanges/{$exchange->id}/peer-respond", [
            'action' => 'reject',
            'note'   => 'Maaf saya ada kegiatan keluarga, tidak bisa menggantikan.',
        ]);

        $rejectResponse->assertRedirect();
        $rejectResponse->assertSessionHas('ok', 'Anda telah menolak permohonan pergantian shift.');

        // Status di database terupdate menjadi rejected
        $exchange->refresh();
        $this->assertSame('rejected', $exchange->status);
        $this->assertSame('Maaf saya ada kegiatan keluarga, tidak bisa menggantikan.', $exchange->replacement_note);
        $this->assertNotNull($exchange->peer_responded_at);

        // Shift tetap milik Pemohon A (tidak berubah)
        $this->assertSame($empA->id, $shift->fresh()->user_id);

        // Verifikasi lanjutan: Manajer tidak dapat menyetujui permohonan yang sudah ditolak rekan (409 Conflict)
        $this->actingAs($manager);
        $mgrTryResponse = $this->post("/shifts/exchanges/{$exchange->id}/review", [
            'decision' => 'approve',
        ]);
        $mgrTryResponse->assertStatus(409);
        $this->assertSame('rejected', $exchange->fresh()->status);
    }
}

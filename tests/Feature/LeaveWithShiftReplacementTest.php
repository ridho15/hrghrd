<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LeaveDay;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveWithShiftReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_show_displays_affected_shifts_and_peer_options(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Pemohon Izin']);
        $peer = $this->createUser(['branch_id' => $branch->id, 'name' => 'Rekan Pengganti']);

        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-20 08:00:00',
            'end_at'   => '2026-10-20 16:00:00',
            'status'   => 'approved',
        ]);

        $leave = LeaveRequest::create([
            'user_id'    => $emp->id,
            'type'       => 'leave',
            'start_date' => '2026-10-20',
            'end_date'   => '2026-10-20',
            'reason'     => 'Acara keluarga mendesak di luar kota',
            'status'     => 'pending',
            'created_by' => $emp->id,
        ]);
        LeaveDay::create([
            'leave_request_id' => $leave->id,
            'date'             => '2026-10-20',
            'status'           => 'pending',
            'paid'             => false,
        ]);

        $response = $this->get('/leave/' . $leave->id);
        $response->assertOk();
        $response->assertSee('Jadwal Shift pada Periode Izin');
        $response->assertSee('Penunjukan Staf Pengganti (Lembur)');
        $response->assertSee('Rekan Pengganti');
        $response->assertSee('name="replacements[' . $shift->id . ']"', false);
    }

    public function test_approving_leave_with_replacement_reassigns_shift_and_creates_records(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Pemohon Sakit']);
        $peer = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staf Lembur']);

        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-22 09:00:00',
            'end_at'   => '2026-10-22 17:00:00',
            'status'   => 'approved',
            'version'  => 1,
        ]);

        $leave = LeaveRequest::create([
            'user_id'    => $emp->id,
            'type'       => 'sick',
            'start_date' => '2026-10-22',
            'end_date'   => '2026-10-22',
            'reason'     => 'Demam tinggi dan istirahat dokter',
            'status'     => 'pending',
            'created_by' => $emp->id,
        ]);
        LeaveDay::create([
            'leave_request_id' => $leave->id,
            'date'             => '2026-10-22',
            'status'           => 'pending',
            'paid'             => false,
        ]);

        $response = $this->post('/leave/' . $leave->id . '/review', [
            'approved_dates' => ['2026-10-22'],
            'paid_dates'     => ['2026-10-22'],
            'review_note'    => 'Izin disetujui, shift dialihkan ke Staf Lembur.',
            'replacements'   => [
                $shift->id => $peer->id,
            ],
        ]);

        $response->assertRedirect('/leave/' . $leave->id);
        $this->assertDatabaseHas('leave_requests', [
            'id'     => $leave->id,
            'status' => 'approved',
        ]);

        // Shift harus dialihkan ke peer
        $shift->refresh();
        $this->assertEquals($peer->id, $shift->user_id);
        $this->assertEquals(2, $shift->version);

        // Rekaman shift_exchanges harus terbentuk
        $this->assertDatabaseHas('shift_exchanges', [
            'requester_shift_id' => $shift->id,
            'requester_id'       => $emp->id,
            'replacement_id'     => $peer->id,
            'status'             => 'approved',
        ]);

        // Jejak audit shift dan leave_request
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'shift',
            'subject_id'   => $shift->id,
            'action'       => 'direct_reassigned',
        ]);
    }

    public function test_shift_not_reassigned_if_date_is_not_approved(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Pemohon Ditolak']);
        $peer = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staf Cadangan']);

        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-25 08:00:00',
            'end_at'   => '2026-10-25 16:00:00',
            'status'   => 'approved',
            'version'  => 1,
        ]);

        $leave = LeaveRequest::create([
            'user_id'    => $emp->id,
            'type'       => 'leave',
            'start_date' => '2026-10-25',
            'end_date'   => '2026-10-25',
            'reason'     => 'Izin keperluan pribadi',
            'status'     => 'pending',
            'created_by' => $emp->id,
        ]);
        LeaveDay::create([
            'leave_request_id' => $leave->id,
            'date'             => '2026-10-25',
            'status'           => 'pending',
            'paid'             => false,
        ]);

        // Review tanpa menyetujui tanggal (approved_dates kosong)
        $response = $this->post('/leave/' . $leave->id . '/review', [
            'approved_dates' => [],
            'review_note'    => 'Mohon maaf izin belum dapat disetujui karena kekurangan staf.',
            'replacements'   => [
                $shift->id => $peer->id,
            ],
        ]);

        $response->assertRedirect('/leave/' . $leave->id);
        $this->assertDatabaseHas('leave_requests', [
            'id'     => $leave->id,
            'status' => 'rejected',
        ]);

        // Shift tetap milik emp, tidak berpindah ke peer
        $shift->refresh();
        $this->assertEquals($emp->id, $shift->user_id);
        $this->assertEquals(1, $shift->version);
    }

    public function test_replacement_with_time_collision_is_prevented(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Pemohon Bentrok']);
        $peer = $this->createUser(['branch_id' => $branch->id, 'name' => 'Rekan Sibuk']);

        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-28 08:00:00',
            'end_at'   => '2026-10-28 16:00:00',
            'status'   => 'approved',
        ]);

        // Rekan sudah punya shift di jam yang sama
        $this->createShift($peer, $branch, [
            'start_at' => '2026-10-28 08:00:00',
            'end_at'   => '2026-10-28 16:00:00',
            'status'   => 'approved',
        ]);

        $leave = LeaveRequest::create([
            'user_id'    => $emp->id,
            'type'       => 'leave',
            'start_date' => '2026-10-28',
            'end_date'   => '2026-10-28',
            'reason'     => 'Izin keperluan keluarga mendesak',
            'status'     => 'pending',
            'created_by' => $emp->id,
        ]);
        LeaveDay::create([
            'leave_request_id' => $leave->id,
            'date'             => '2026-10-28',
            'status'           => 'pending',
            'paid'             => false,
        ]);

        $response = $this->post('/leave/' . $leave->id . '/review', [
            'approved_dates' => ['2026-10-28'],
            'review_note'    => 'Disetujui dengan pengalihan ke Rekan Sibuk.',
            'replacements'   => [
                $shift->id => $peer->id,
            ],
        ]);

        // Harapannya ditolak dengan 409 status code
        $response->assertStatus(409);
    }
}

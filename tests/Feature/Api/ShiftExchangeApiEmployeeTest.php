<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Position;
use App\Models\Shift;
use App\Models\ShiftExchange;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftExchangeApiEmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function createOpenShift(Branch $branch, ?Position $position = null, array $attributes = []): Shift
    {
        return Shift::create(array_merge([
            'user_id'     => null,
            'position_id' => $position?->id,
            'branch_id'   => $branch->id,
            'start_at'    => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0)->toDateTimeString(),
            'end_at'      => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0)->toDateTimeString(),
            'status'      => 'approved',
            'version'     => 1,
        ], $attributes));
    }

    public function test_employee_can_request_shift_swap_via_mobile_api(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Barista']);

        $empA = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);
        $empB = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
        ]);

        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0)->toDateTimeString(),
        ]);

        $this->actingAs($empA);
        $res = $this->postJson("/api/v1/shifts/{$shiftA->id}/exchange", [
            'type'            => 'swap',
            'target_shift_id' => $shiftB->id,
            'reason'          => 'Tukar jadwal untuk acara keluarga hari Selasa',
        ]);

        $res->assertStatus(201);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.status', 'pending_peer');
        $res->assertJsonPath('data.type', 'swap');
        $res->assertJsonPath('data.requester_id', $empA->id);
        $res->assertJsonPath('data.replacement_id', $empB->id);

        $this->assertDatabaseHas('shift_exchanges', [
            'requester_id'       => $empA->id,
            'requester_shift_id' => $shiftA->id,
            'target_shift_id'    => $shiftB->id,
            'replacement_id'     => $empB->id,
            'status'             => 'pending_peer',
        ]);
    }

    public function test_employee_can_request_replacement_via_mobile_api(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Kasir']);

        $empA = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);
        $empB = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
        ]);

        $this->actingAs($empA);
        $res = $this->postJson("/api/v1/shifts/{$shiftA->id}/exchange", [
            'type'           => 'replacement',
            'replacement_id' => $empB->id,
            'reason'         => 'Berhalangan hadir dan digantikan rekan B',
        ]);

        $res->assertStatus(201);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.status', 'pending_peer');
        $res->assertJsonPath('data.type', 'replacement');
        $res->assertJsonPath('data.replacement_id', $empB->id);
    }

    public function test_employee_cannot_request_exchange_for_other_users_shift(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $empB = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
        ]);

        // Karyawan A mencoba mengajukan exchange atas shift milik Karyawan B
        $this->actingAs($empA);
        $res = $this->postJson("/api/v1/shifts/{$shiftB->id}/exchange", [
            'type'           => 'replacement',
            'replacement_id' => $empA->id,
            'reason'         => 'Mencoba mengajukan untuk shift orang lain',
        ]);

        $res->assertStatus(403);
        $this->assertStringContainsString('milik Anda sendiri', $res->json('message'));
    }

    public function test_employee_cannot_claim_open_slot_using_other_users_shift(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Staf']);

        $empA = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);
        $empB = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);

        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
        ]);

        $openSlot = $this->createOpenShift($branch, $pos);

        // Karyawan A mencoba ganti shift ke open slot menggunakan shift milik Karyawan B
        $this->actingAs($empA);
        $res = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'               => 'change_shift',
            'target_shift_id'    => $openSlot->id,
            'requester_shift_id' => $shiftB->id,
            'reason'             => 'Mencoba pakai shift rekan untuk klaim open slot',
        ]);

        $res->assertStatus(403);
        $this->assertStringContainsString('milik Anda sendiri', $res->json('message'));
    }

    public function test_employee_cannot_claim_open_slot_on_behalf_of_other_user(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Staf']);

        $empA = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);
        $empB = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);

        $openSlot = $this->createOpenShift($branch, $pos);

        // Karyawan A mencoba mengajukan atas nama Karyawan B
        $this->actingAs($empA);
        $res = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'user_id'         => $empB->id,
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'off_date'        => Carbon::now('Asia/Jakarta')->addDays(1)->toDateString(),
            'reason'          => 'Mengajukan atas nama rekan B',
        ]);

        $res->assertStatus(403);
        $this->assertStringContainsString('karyawan lain', $res->json('message'));
    }

    public function test_full_lifecycle_employee_swap_peer_approval_and_manager_review(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Barista']);

        $empA = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);
        $empB = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
        ]);

        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0)->toDateTimeString(),
        ]);

        // 1. Karyawan A submit swap
        $this->actingAs($empA);
        $resStore = $this->postJson("/api/v1/shifts/{$shiftA->id}/exchange", [
            'type'            => 'swap',
            'target_shift_id' => $shiftB->id,
            'reason'          => 'Tukar jadwal kerja hari Rabu dan Kamis',
        ]);
        $resStore->assertStatus(201);
        $exchangeId = $resStore->json('data.id');

        // 2. Karyawan B menerima (peer-respond accept)
        $this->actingAs($empB);
        $resPeer = $this->postJson("/api/v1/shifts/exchanges/{$exchangeId}/peer-respond", [
            'action' => 'accept',
            'note'   => 'Siap bertukar jadwal',
        ]);
        $resPeer->assertOk();
        $this->assertSame('pending_manager', $resPeer->json('data.status'));

        // 3. Manajer menyetujui (manager-review approve)
        $this->actingAs($manager);
        $resReview = $this->postJson("/api/v1/shifts/exchanges/{$exchangeId}/review", [
            'decision' => 'approve',
            'note'     => 'Disetujui manajer cabang',
        ]);
        $resReview->assertOk();
        $this->assertSame('approved', $resReview->json('data.status'));

        // 4. Verifikasi kepemilikan shift bertukar di database
        $this->assertSame($empB->id, $shiftA->fresh()->user_id);
        $this->assertSame($empA->id, $shiftB->fresh()->user_id);
    }
}

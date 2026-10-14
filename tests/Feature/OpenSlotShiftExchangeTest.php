<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Position;
use App\Models\Shift;
use App\Models\ShiftExchange;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenSlotShiftExchangeTest extends TestCase
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

    /**
     * Test: API GET /api/v1/shifts/open-slots
     * Memastikan hanya slot kosong cabang pemohon dengan level setingkat yang dikembalikan.
     */
    public function test_employee_can_list_available_open_slots_for_matching_position_level_only(): void
    {
        $branchA = $this->createBranch(['name' => 'Cabang A']);
        $branchB = $this->createBranch(['name' => 'Cabang B']);

        $posStaff = $this->createPosition(['name' => 'Barista']);
        $posSupervisor = $this->createPosition(['name' => 'Supervisor']);

        $employee = $this->createUser([
            'branch_id'   => $branchA->id,
            'position_id' => $posStaff->id,
            'role'        => 'employee',
        ]);

        // 1. Slot kosong cabang A - level setingkat (Barista) -> Harusnya MUNCUL
        $slot1 = $this->createOpenShift($branchA, $posStaff, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
        ]);

        // 2. Slot kosong cabang A - umum (position_id null) -> Harusnya MUNCUL
        $slot2 = $this->createOpenShift($branchA, null, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(9, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(17, 0)->toDateTimeString(),
        ]);

        // 3. Slot kosong cabang A - level beda (Supervisor) -> TIDAK boleh muncul
        $slot3 = $this->createOpenShift($branchA, $posSupervisor, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(4)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(4)->setTime(16, 0)->toDateTimeString(),
        ]);

        // 4. Slot kosong cabang B -> TIDAK boleh muncul (cabang beda)
        $slot4 = $this->createOpenShift($branchB, $posStaff, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
        ]);

        // 5. Shift yang sudah terisi user_id -> TIDAK boleh muncul
        $slot5 = $this->createShift($employee, $branchA, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(5)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(5)->setTime(16, 0)->toDateTimeString(),
        ]);

        $this->actingAs($employee);
        $response = $this->getJson('/api/v1/shifts/open-slots');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $slotIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($slot1->id, $slotIds);
        $this->assertContains($slot2->id, $slotIds);
        $this->assertNotContains($slot3->id, $slotIds, 'Slot untuk level supervisor tidak boleh muncul untuk barista');
        $this->assertNotContains($slot4->id, $slotIds, 'Slot cabang lain tidak boleh muncul');
        $this->assertNotContains($slot5->id, $slotIds, 'Shift yang sudah berpenghuni tidak boleh muncul');
    }

    /**
     * Test: API POST /api/v1/shifts/exchanges/open-slot (Ganti Shift)
     * Karyawan mengajukan ganti shift kerja ke slot jadwal kosong setingkat.
     */
    public function test_employee_can_request_change_shift_to_open_slot_via_api(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Kasir']);

        $employee = $this->createUser([
            'branch_id'   => $branch->id,
            'position_id' => $pos->id,
            'role'        => 'employee',
        ]);

        $myShift = $this->createShift($employee, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
        ]);

        $openSlot = $this->createOpenShift($branch, $pos, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(5)->setTime(13, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(5)->setTime(21, 0)->toDateTimeString(),
        ]);

        // Karyawan dilarang mengajukan (403)
        $this->actingAs($employee);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'               => 'change_shift',
            'target_shift_id'    => $openSlot->id,
            'requester_shift_id' => $myShift->id,
            'reason'             => 'Ingin pindah shift karena ada urusan keluarga di hari Selasa',
        ]);
        $resEmp->assertStatus(403);

        // Manajer mengajukan ganti shift terpusat
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $response = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'               => 'change_shift',
            'target_shift_id'    => $openSlot->id,
            'requester_shift_id' => $myShift->id,
            'reason'             => 'Ingin pindah shift karena ada urusan keluarga di hari Selasa',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data'    => [
                'type'            => 'change_shift',
                'status'          => 'pending_manager',
                'target_shift_id' => $openSlot->id,
                'requester_id'    => $employee->id,
                'replacement_id'  => null,
            ],
        ]);

        $this->assertDatabaseHas('shift_exchanges', [
            'type'               => 'change_shift',
            'target_shift_id'    => $openSlot->id,
            'requester_shift_id' => $myShift->id,
            'requester_id'       => $employee->id,
            'replacement_id'     => null,
            'status'             => 'pending_manager',
        ]);
    }

    /**
     * Test: API POST /api/v1/shifts/exchanges/open-slot (Ganti Libur)
     * Karyawan dilarang mengajukan mandiri (403), manajer dapat memproses terpusat (201).
     */
    public function test_employee_can_request_change_off_day_to_open_slot_via_api(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Staf Gudang']);

        $employee = $this->createUser([
            'branch_id'   => $branch->id,
            'position_id' => $pos->id,
            'role'        => 'employee',
        ]);

        $openSlot = $this->createOpenShift($branch, $pos, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(6)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(6)->setTime(16, 0)->toDateTimeString(),
        ]);

        $offDate = Carbon::now('Asia/Jakarta')->addDays(1)->toDateString();

        // Karyawan dilarang (403)
        $this->actingAs($employee);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'off_date'        => $offDate,
            'reason'          => 'Ganti libur hari Senin untuk masuk pada shift kosong hari Sabtu',
        ]);
        $resEmp->assertStatus(403);

        // Manajer memproses terpusat
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $response = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'user_id'         => $employee->id,
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'off_date'        => $offDate,
            'reason'          => 'Ganti libur hari Senin untuk masuk pada shift kosong hari Sabtu',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data'    => [
                'type'            => 'change_off_day',
                'status'          => 'pending_manager',
                'target_shift_id' => $openSlot->id,
                'target_date'     => $offDate,
            ],
        ]);

        $this->assertDatabaseHas('shift_exchanges', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'target_date'     => $offDate,
            'status'          => 'pending_manager',
        ]);
    }

    /**
     * Test Negatif: Level Jabatan Tidak Setingkat (422)
     */
    public function test_negative_cannot_claim_open_slot_of_different_position_level(): void
    {
        $branch = $this->createBranch();
        $posStaff = $this->createPosition(['name' => 'Junior Staff']);
        $posLeader = $this->createPosition(['name' => 'Team Leader']);

        $employee = $this->createUser([
            'branch_id'   => $branch->id,
            'position_id' => $posStaff->id,
            'role'        => 'employee',
        ]);

        // Slot kosong ditujukan khusus untuk Team Leader
        $openSlotLeader = $this->createOpenShift($branch, $posLeader, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(4)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(4)->setTime(16, 0)->toDateTimeString(),
        ]);

        // Karyawan dilarang mengajukan (403)
        $this->actingAs($employee);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlotLeader->id,
            'reason'          => 'Mencoba mengambil slot leader',
        ]);
        $resEmp->assertStatus(403);

        // Manajer yang memproses ditolak 422 karena level posisi berbeda
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $response = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlotLeader->id,
            'reason'          => 'Mencoba mengambil slot leader',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('level jabatan yang berbeda', $response->json('message'));
    }

    /**
     * Test Negatif: Cabang Berbeda (422)
     */
    public function test_negative_cannot_claim_open_slot_of_different_branch(): void
    {
        $branchA = $this->createBranch(['name' => 'Cabang Jakarta']);
        $branchB = $this->createBranch(['name' => 'Cabang Surabaya']);
        $pos = $this->createPosition(['name' => 'Staff']);

        $employee = $this->createUser([
            'branch_id'   => $branchA->id,
            'position_id' => $pos->id,
            'role'        => 'employee',
        ]);

        $openSlotB = $this->createOpenShift($branchB, $pos, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0)->toDateTimeString(),
        ]);

        $this->actingAs($employee);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlotB->id,
            'reason'          => 'Mencoba ambil slot cabang lain',
        ]);
        $resEmp->assertStatus(403);

        $managerA = $this->createUser(['role' => 'manager', 'branch_id' => $branchA->id]);
        $this->actingAs($managerA);
        $response = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlotB->id,
            'reason'          => 'Mencoba ambil slot cabang lain',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('cabang yang sama', $response->json('message'));
    }

    /**
     * Test Negatif: Mencoba Mengambil Shift yang Bukan Slot Kosong (422)
     */
    public function test_negative_cannot_claim_slot_that_is_already_assigned(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition();

        $employeeA = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id]);
        $employeeB = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id]);

        $assignedShift = $this->createShift($employeeB, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0)->toDateTimeString(),
        ]);

        $this->actingAs($employeeA);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $assignedShift->id,
            'reason'          => 'Mencoba serobot shift rekan via open slot endpoint',
        ]);
        $resEmp->assertStatus(403);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $response = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $assignedShift->id,
            'reason'          => 'Mencoba serobot shift rekan via open slot endpoint',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('bukan merupakan jadwal kosong', $response->json('message'));
    }

    /**
     * Test Negatif: Jam Bentrok dengan Jadwal Lain Milik Pemohon (409)
     */
    public function test_negative_cannot_claim_open_slot_if_schedule_overlaps(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition();

        $employee = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id]);

        // Karyawan sudah punya jadwal di hari ke-3 jam 10:00 - 18:00
        $existingShift = $this->createShift($employee, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(18, 0)->toDateTimeString(),
        ]);

        // Slot kosong di hari ke-3 jam 08:00 - 16:00 (Bentrok di jam 10:00 - 16:00)
        $openSlot = $this->createOpenShift($branch, $pos, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0)->toDateTimeString(),
        ]);

        $this->actingAs($employee);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'reason'          => 'Mencoba ambil slot bertabrakan',
        ]);
        $resEmp->assertStatus(403);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $response = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'user_id'         => $employee->id,
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'reason'          => 'Mencoba ambil slot bertabrakan',
        ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('bertabrakan', $response->json('message'));
    }

    /**
     * Test Negatif: Slot Kosong Sedang Diperebutkan Oleh Permohonan Pending Lain (409)
     */
    public function test_negative_cannot_claim_open_slot_with_pending_exchange(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition();

        $employee1 = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id]);
        $employee2 = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id]);

        $openSlot = $this->createOpenShift($branch, $pos, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(4)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(4)->setTime(16, 0)->toDateTimeString(),
        ]);

        // Karyawan 1 sudah mengajukan permohonan terlebih dahulu
        ShiftExchange::create([
            'type'            => 'change_off_day',
            'branch_id'       => $branch->id,
            'requester_id'    => $employee1->id,
            'target_shift_id' => $openSlot->id,
            'reason'          => 'Pengajuan karyawan 1',
            'status'          => 'pending_manager',
        ]);

        // Karyawan 2 mencoba mengajukan ke slot yang sama
        $this->actingAs($employee2);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'reason'          => 'Karyawan 2 berebut slot',
        ]);
        $resEmp->assertStatus(403);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $response = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'user_id'         => $employee2->id,
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'reason'          => 'Karyawan 2 berebut slot',
        ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('sudah memiliki permohonan', $response->json('message'));
    }

    /**
     * Test Negatif: Ganti Shift Tanpa Menyertakan Shift Asal (422)
     */
    public function test_negative_change_shift_requires_requester_shift(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition();
        $employee = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id]);

        $openSlot = $this->createOpenShift($branch, $pos);

        $this->actingAs($employee);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_shift',
            'target_shift_id' => $openSlot->id,
            'reason'          => 'Ganti shift tanpa shift asal',
        ]);
        $resEmp->assertStatus(403);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $response = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_shift',
            'target_shift_id' => $openSlot->id,
            'reason'          => 'Ganti shift tanpa shift asal',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test Persetujuan Manajer (HRD / Super Admin) untuk Ganti Shift:
     * Slot kosong berpindah ke karyawan, shift asal karyawan dihapus (karyawan libur di hari asal).
     */
    public function test_manager_approves_change_shift_transfers_slot_and_deletes_old_shift(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Barista']);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);

        $myOldShift = $this->createShift($employee, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
        ]);

        $openSlot = $this->createOpenShift($branch, $pos, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(5)->setTime(13, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(5)->setTime(21, 0)->toDateTimeString(),
        ]);

        // Karyawan dilarang mengajukan (403)
        $this->actingAs($employee);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'               => 'change_shift',
            'target_shift_id'    => $openSlot->id,
            'requester_shift_id' => $myOldShift->id,
            'reason'             => 'Tukar hari kerja dengan slot kosong',
        ]);
        $resEmp->assertStatus(403);

        // Manajer mengajukan terpusat
        $this->actingAs($manager);
        $resStore = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'               => 'change_shift',
            'target_shift_id'    => $openSlot->id,
            'requester_shift_id' => $myOldShift->id,
            'reason'             => 'Tukar hari kerja dengan slot kosong',
        ]);
        $resStore->assertStatus(201);
        $exchangeId = $resStore->json('data.id');

        // Manajer mereview dan menyetujui
        $resReview = $this->postJson("/api/v1/shifts/exchanges/{$exchangeId}/review", [
            'decision' => 'approve',
            'note'     => 'Disetujui manajer cabang',
        ]);

        $resReview->assertOk();
        $resReview->assertJson(['success' => true]);

        // Verifikasi hasil:
        // 1. Slot kosong sekarang menjadi milik employee
        $freshOpenSlot = $openSlot->fresh();
        $this->assertSame($employee->id, $freshOpenSlot->user_id);
        $this->assertSame('approved', $freshOpenSlot->status);

        // 2. Shift lama karyawan telah dikosongkan (hari tersebut menjadi libur bagi karyawan, dan menjadi open slot)
        $this->assertNull($myOldShift->fresh()->user_id);

        // 3. Status exchange menjadi approved
        $this->assertDatabaseHas('shift_exchanges', [
            'id'          => $exchangeId,
            'status'      => 'approved',
            'reviewed_by' => $manager->id,
        ]);
    }

    /**
     * Test Penolakan Manajer:
     * Slot kosong tetap berstatus null, shift asal karyawan tetap aman.
     */
    public function test_manager_rejects_open_slot_exchange_keeps_slot_unassigned(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition();

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id]);

        $myShift = $this->createShift($employee, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
        ]);

        $openSlot = $this->createOpenShift($branch, $pos);

        // Manajer memproses permohonan terpusat
        $this->actingAs($manager);
        $resStore = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'               => 'change_shift',
            'target_shift_id'    => $openSlot->id,
            'requester_shift_id' => $myShift->id,
            'reason'             => 'Alasan kurang mendesak',
        ]);
        $resStore->assertStatus(201);
        $exchangeId = $resStore->json('data.id');

        // Manajer menolak
        $resReview = $this->postJson("/api/v1/shifts/exchanges/{$exchangeId}/review", [
            'decision' => 'reject',
            'note'     => 'Kebutuhan tenaga kerja di hari asal masih tinggi',
        ]);

        $resReview->assertOk();

        // Verifikasi hasil:
        // Slot tetap null
        $this->assertNull($openSlot->fresh()->user_id);
        // Shift lama karyawan tetap ada
        $this->assertNotNull($myShift->fresh());
        // Status exchange rejected
        $this->assertDatabaseHas('shift_exchanges', [
            'id'     => $exchangeId,
            'status' => 'rejected',
        ]);
    }

    /**
     * Test: Alur Web Blade Interface
     * Menampilkan informasi jadwal kosong yang diajukan pada panel Super Admin / HRD.
     */
    public function test_web_interface_displays_open_slot_info_and_allows_manager_approval(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Kitchen Staff']);

        $superAdmin = $this->createUser(['role' => 'admin']);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'position_id' => $pos->id, 'name' => 'Budi Santoso']);

        $openSlot = $this->createOpenShift($branch, $pos, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(4)->setTime(9, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(4)->setTime(17, 0)->toDateTimeString(),
        ]);

        // Submit via Web Route oleh karyawan dilarang (403)
        $this->actingAs($employee);
        $resWebEmp = $this->post('/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'target_date'     => Carbon::now('Asia/Jakarta')->addDays(1)->toDateString(),
            'reason'          => 'Permohonan ganti libur untuk ambil jadwal kosong dapur',
        ]);
        $resWebEmp->assertForbidden();

        // Submit via Web Route oleh Super Admin / HRD
        $this->actingAs($superAdmin);
        $resWebStore = $this->post('/shifts/exchanges/open-slot', [
            'user_id'         => $employee->id,
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'target_date'     => Carbon::now('Asia/Jakarta')->addDays(1)->toDateString(),
            'reason'          => 'Permohonan ganti libur untuk ambil jadwal kosong dapur',
        ]);
        $resWebStore->assertRedirect(route('shifts.exchanges'));
        $resWebStore->assertSessionHas('ok');

        $exchange = ShiftExchange::where('target_shift_id', $openSlot->id)->first();
        $this->assertNotNull($exchange);
        $this->assertSame('pending_manager', $exchange->status);

        // Super Admin / HRD membuka halaman permohonan exchange
        $this->actingAs($superAdmin);
        $resView = $this->get('/shifts/exchanges');
        $resView->assertOk();

        // Memastikan memunculkan informasi jadwal kosong yang diajukan karyawan tersebut
        $resView->assertSee('Slot Jadwal Kosong');
        $resView->assertSee('Ganti Libur (Slot Kosong)');
        $resView->assertSee('Informasi Jadwal Kosong yang Diajukan');
        $resView->assertSee('Kitchen Staff');
        $resView->assertSee('Budi Santoso');
        $resView->assertSee('Setujui');
        $resView->assertSee('Tolak');

        // Super Admin menyetujui langsung melalui web form
        $resApprove = $this->post("/shifts/exchanges/{$exchange->id}/review", [
            'decision' => 'approve',
            'note'     => 'Disetujui Super Admin',
        ]);
        $resApprove->assertRedirect();
        $resApprove->assertSessionHas('ok');

        // Verifikasi kepemilikan slot
        $this->assertSame($employee->id, $openSlot->fresh()->user_id);
    }
}

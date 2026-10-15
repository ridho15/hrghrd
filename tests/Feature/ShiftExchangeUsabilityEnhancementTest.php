<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\ShiftExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftExchangeUsabilityEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_view_renders_intent_driven_exchange_form(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Pemohon', 'role' => 'employee']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Rekan', 'role' => 'employee']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => now('Asia/Jakarta')->addDays(3)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addDays(3)->setTime(16, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => now('Asia/Jakarta')->addDays(4)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addDays(4)->setTime(16, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $this->actingAs($empA);
        $response = $this->get(route('shifts.calendar'));

        $response->assertStatus(200);
        // Memastikan formulir berbasis niat (Intent-Driven) tersedia
        $response->assertSee('Ajukan Pergantian / Tukar Shift');
        $response->assertSee('Saya Ingin Libur');
        $response->assertSee('Saya Ingin Masuk Kerja');
        $response->assertSee('Minta Rekan Gantikan (Searah)');
        $response->assertSee('Saling Bertukar Hari (2 Arah)');
        $response->assertSee('Pratinjau Hasil Perubahan Jadwal');
        $response->assertSee('Two-Tier Approval Gate');
        // Memastikan atribut smart cascading tersedia
        $response->assertSee('data-user-id="' . $empB->id . '"', false);
    }

    public function test_shifts_exchange_index_view_renders_standalone_modal_and_quick_action_button(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Pemohon', 'role' => 'employee']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Rekan', 'role' => 'employee']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => now('Asia/Jakarta')->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addDays(2)->setTime(16, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $this->actingAs($empA);
        $response = $this->get(route('shifts.exchanges'));

        $response->assertStatus(200);
        // Memastikan tombol + Ajukan Pergantian / Tukar Shift ada di header
        $response->assertSee('Ajukan Pergantian / Tukar Shift');
        $response->assertSee('data-open-modal="modal-create-exchange"', false);
        // Memastikan modal standalone dirender
        $response->assertSee('id="modal-create-exchange"', false);
        $response->assertSee('Pilih Jadwal Kerja Anda yang Ingin Diganti / Ditukar');
    }

    public function test_employee_can_submit_replacement_intent_successfully(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Pemohon', 'role' => 'employee']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Rekan', 'role' => 'employee']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => now('Asia/Jakarta')->addDays(5)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addDays(5)->setTime(16, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $this->actingAs($empA);
        $response = $this->post(route('shifts.exchange.store', $shiftA->id), [
            'type' => 'replacement',
            'replacement_id' => $empB->id,
            'reason' => 'Ada acara keluarga di luar kota dan perlu izin libur',
        ]);

        $response->assertRedirect(route('shifts.exchanges'));
        $this->assertDatabaseHas('shift_exchanges', [
            'type' => 'replacement',
            'requester_shift_id' => $shiftA->id,
            'requester_id' => $empA->id,
            'replacement_id' => $empB->id,
            'status' => 'pending_peer',
        ]);
    }

    public function test_employee_can_submit_swap_intent_successfully(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Pemohon', 'role' => 'employee']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Rekan', 'role' => 'employee']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => now('Asia/Jakarta')->addDays(5)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addDays(5)->setTime(16, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => now('Asia/Jakarta')->addDays(6)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addDays(6)->setTime(16, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $this->actingAs($empA);
        $response = $this->post(route('shifts.exchange.store', $shiftA->id), [
            'type' => 'swap',
            'target_shift_id' => $shiftB->id,
            'reason' => 'Tukar shift karena ada jadwal kuliah malam',
        ]);

        $response->assertRedirect(route('shifts.exchanges'));
        $this->assertDatabaseHas('shift_exchanges', [
            'type' => 'swap',
            'requester_shift_id' => $shiftA->id,
            'target_shift_id' => $shiftB->id,
            'requester_id' => $empA->id,
            'replacement_id' => $empB->id,
            'status' => 'pending_peer',
        ]);
    }

    public function test_employee_can_submit_open_slot_change_shift_successfully(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Pemohon', 'role' => 'employee']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => now('Asia/Jakarta')->addDays(7)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addDays(7)->setTime(16, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        // Slot kosong di cabang
        $openSlot = Shift::create([
            'branch_id' => $branch->id,
            'user_id' => null,
            'start_at' => now('Asia/Jakarta')->addDays(8)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addDays(8)->setTime(16, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $this->actingAs($empA);
        $response = $this->post(route('shifts.exchange.store', $shiftA->id), [
            'type' => 'change_shift',
            'target_shift_id' => $openSlot->id,
            'reason' => 'Mengambil slot kosong di hari berikutnya dan meliburkan shift ini',
        ]);

        $response->assertRedirect(route('shifts.exchanges'));
        $this->assertDatabaseHas('shift_exchanges', [
            'type' => 'change_shift',
            'requester_shift_id' => $shiftA->id,
            'target_shift_id' => $openSlot->id,
            'status' => 'pending_manager',
        ]);
    }

    public function test_employee_can_submit_open_slot_change_off_day_successfully(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Pemohon', 'role' => 'employee']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => now('Asia/Jakarta')->addDays(7)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addDays(7)->setTime(16, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $openSlot = Shift::create([
            'branch_id' => $branch->id,
            'user_id' => null,
            'start_at' => now('Asia/Jakarta')->addDays(9)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->addDays(9)->setTime(16, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $this->actingAs($empA);
        $response = $this->post(route('shifts.exchange.store', $shiftA->id), [
            'type' => 'change_off_day',
            'target_shift_id' => $openSlot->id,
            'target_date' => now('Asia/Jakarta')->addDays(12)->toDateString(),
            'reason' => 'Mengambil jadwal kosong dan menukar hari libur ke tanggal lain',
        ]);

        $response->assertRedirect(route('shifts.exchanges'));
        $this->assertDatabaseHas('shift_exchanges', [
            'type' => 'change_off_day',
            'requester_shift_id' => $shiftA->id,
            'target_shift_id' => $openSlot->id,
            'status' => 'pending_manager',
        ]);
    }
}

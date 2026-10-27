<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\Shift;
use App\Models\ShiftExchange;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftExchangeNonHappyPathUatTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Position $position;
    private User $employee;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['name' => 'Cabang Surabaya']);
        $this->position = Position::factory()->create(['name' => 'Barista']);

        $this->employee = User::factory()->create([
            'name' => 'Budi Santoso',
            'role' => 'employee',
            'branch_id' => $this->branch->id,
            'position_id' => $this->position->id,
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Manager Surabaya',
            'role' => 'manager',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_manager_cannot_reject_shift_exchange_without_a_note(): void
    {
        $openSlot = Shift::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => null,
            'position_id' => $this->position->id,
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0),
            'end_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0),
            'status' => 'approved',
        ]);

        $exchange = ShiftExchange::create([
            'requester_id' => $this->employee->id,
            'target_shift_id' => $openSlot->id,
            'type' => 'change_off_day',
            'reason' => 'Ingin masuk di hari libur',
            'status' => 'pending_manager',
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->post("/shifts/exchanges/{$exchange->id}/review", [
                'decision' => 'reject',
                'note' => '', // Empty note
            ]);

        $response->assertSessionHasErrors(['note']);
        $this->assertEquals('pending_manager', $exchange->fresh()->status);
    }

    public function test_manager_can_reject_shift_exchange_with_valid_note(): void
    {
        $openSlot = Shift::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => null,
            'position_id' => $this->position->id,
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0),
            'end_at' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0),
            'status' => 'approved',
        ]);

        $exchange = ShiftExchange::create([
            'requester_id' => $this->employee->id,
            'target_shift_id' => $openSlot->id,
            'type' => 'change_off_day',
            'reason' => 'Ingin masuk di hari libur',
            'status' => 'pending_manager',
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->post("/shifts/exchanges/{$exchange->id}/review", [
                'decision' => 'reject',
                'note' => 'Kuota tim pada hari tersebut sudah mencukupi.',
            ]);

        $response->assertRedirect();
        $this->assertEquals('rejected', $exchange->fresh()->status);
        $this->assertEquals('Kuota tim pada hari tersebut sudah mencukupi.', $exchange->fresh()->review_note);
    }

    public function test_employee_cannot_claim_open_slot_if_approved_leave_exists_on_that_date(): void
    {
        $targetDate = Carbon::now('Asia/Jakarta')->addDays(4);

        LeaveRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'start_date' => $targetDate->toDateString(),
            'end_date' => $targetDate->toDateString(),
            'reason' => 'Acara keluarga',
        ]);

        $openSlot = Shift::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => null,
            'position_id' => $this->position->id,
            'start_at' => $targetDate->copy()->setTime(8, 0),
            'end_at' => $targetDate->copy()->setTime(16, 0),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->post('/shifts/exchanges/open-slot', [
                'type' => 'change_off_day',
                'target_shift_id' => $openSlot->id,
                'target_date' => $targetDate->toDateString(),
                'reason' => 'Coba ambil saat sedang cuti',
            ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('cuti/izin', session('error'));
        $this->assertDatabaseMissing('shift_exchanges', [
            'requester_id' => $this->employee->id,
            'target_shift_id' => $openSlot->id,
        ]);
    }

    public function test_employee_cannot_claim_expired_past_open_slot(): void
    {
        $pastDate = Carbon::now('Asia/Jakarta')->subDays(2);

        $pastOpenSlot = Shift::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => null,
            'position_id' => $this->position->id,
            'start_at' => $pastDate->copy()->setTime(8, 0),
            'end_at' => $pastDate->copy()->setTime(16, 0),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->post('/shifts/exchanges/open-slot', [
                'type' => 'change_off_day',
                'target_shift_id' => $pastOpenSlot->id,
                'reason' => 'Coba ambil jadwal kemarin',
            ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('sudah lewat', session('error'));
    }

    public function test_double_shift_conflict_returns_friendly_error_without_crashing(): void
    {
        $targetDate = Carbon::now('Asia/Jakarta')->addDays(5);

        // Employee already has a shift on target date
        Shift::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->employee->id,
            'start_at' => $targetDate->copy()->setTime(8, 0),
            'end_at' => $targetDate->copy()->setTime(16, 0),
            'status' => 'approved',
        ]);

        // Open slot on the same time
        $openSlot = Shift::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => null,
            'position_id' => $this->position->id,
            'start_at' => $targetDate->copy()->setTime(8, 0),
            'end_at' => $targetDate->copy()->setTime(16, 0),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employee)
            ->post('/shifts/exchanges/open-slot', [
                'type' => 'change_off_day',
                'target_shift_id' => $openSlot->id,
                'reason' => 'Mencoba ambil slot bertabrakan',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('bertabrakan', session('error'));
    }

    public function test_employee_can_view_open_slot_banner_and_claim_buttons_on_calendar(): void
    {
        $targetDate = Carbon::now('Asia/Jakarta')->addDays(1);

        $openSlot = Shift::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => null,
            'position_id' => $this->position->id,
            'start_at' => $targetDate->copy()->setTime(8, 0),
            'end_at' => $targetDate->copy()->setTime(16, 0),
            'status' => 'approved',
        ]);

        // Default scope (me): employee sees the banner announcing open slots
        $responseMe = $this->actingAs($this->employee)
            ->get('/shifts/calendar?month=' . $targetDate->format('Y-m'));

        $responseMe->assertOk();
        $responseMe->assertSee('Jadwal Kosong Tersedia');
        $responseMe->assertSee('Roster Cabang');

        // Branch scope: employee sees the open slot card, modal, and action button
        $responseBranch = $this->actingAs($this->employee)
            ->get('/shifts/calendar?scope=branch');

        $responseBranch->assertOk();
        $responseBranch->assertSee('modal-claim-open-slot-' . $openSlot->id);
        $responseBranch->assertSee('Ajukan Ambil Jadwal Ini');
    }
}

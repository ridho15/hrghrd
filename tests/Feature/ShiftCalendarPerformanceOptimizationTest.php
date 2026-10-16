<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftCalendarPerformanceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_uses_single_dynamic_modals_instead_of_rendering_per_shift_modals(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch(['name' => 'Cabang Pusat']);

        $emp1 = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staf Satu']);
        $emp2 = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staf Dua']);

        $shifts = [];
        for ($i = 1; $i <= 10; $i++) {
            $user = $i % 2 === 0 ? $emp1 : $emp2;
            $shifts[] = $this->createShift($user, $branch, [
                'start_at' => sprintf('2026-10-%02d 08:00:00', 10 + $i),
                'end_at'   => sprintf('2026-10-%02d 16:00:00', 10 + $i),
                'status'   => 'approved',
            ]);
        }

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();

        $content = $response->getContent();

        // 1. Memastikan 3 Single Dynamic Modal tersedia di DOM
        $response->assertSee('id="modal-shift-detail"', false);
        $response->assertSee('id="modal-exchange-shift-dynamic"', false);
        $response->assertSee('id="modal-reassign-shift-dynamic"', false);

        // 2. Memastikan TIDAK ADA duplikasi modal detail per-shift id di dalam DOM
        foreach ($shifts as $s) {
            $this->assertStringNotContainsString(
                'id="modal-shift-' . $s->id . '"',
                $content,
                "Modal per-shift id modal-shift-{$s->id} tidak boleh dirender berulang di DOM"
            );
            $this->assertStringNotContainsString(
                'id="modal-exchange-shift-' . $s->id . '"',
                $content,
                "Modal per-shift id modal-exchange-shift-{$s->id} tidak boleh dirender berulang di DOM"
            );
            $this->assertStringNotContainsString(
                'id="modal-reassign-shift-' . $s->id . '"',
                $content,
                "Modal per-shift id modal-reassign-shift-{$s->id} tidak boleh dirender berulang di DOM"
            );
        }

        // 3. Memastikan payload dictionary window.shiftsMap disuntikkan ke script
        $response->assertSee('window.shiftsMap =', false);
        $response->assertSee('window.openShiftDetailModal', false);
        $response->assertSee('window.openDynamicExchangeModal', false);
        $response->assertSee('window.openDynamicReassignModal', false);
    }

    public function test_super_admin_calendar_avoids_n_plus_one_queries(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch(['name' => 'Cabang Induk']);

        for ($i = 1; $i <= 12; $i++) {
            $user = $this->createUser(['branch_id' => $branch->id, 'name' => 'User ' . $i]);
            $shift = $this->createShift($user, $branch, [
                'start_at' => sprintf('2026-10-%02d 08:00:00', 10 + $i),
                'end_at'   => sprintf('2026-10-%02d 16:00:00', 10 + $i),
                'status'   => 'approved',
            ]);

            if ($i % 2 === 0) {
                Attendance::create([
                    'user_id'     => $user->id,
                    'shift_id'    => $shift->id,
                    'branch_id'   => $branch->id,
                    'date'        => sprintf('2026-10-%02d', 10 + $i),
                    'checkin_at'  => sprintf('2026-10-%02d 07:55:00', 10 + $i),
                    'checkout_at' => sprintf('2026-10-%02d 16:05:00', 10 + $i),
                    'status'      => 'present',
                ]);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Memastikan query tetap bounded O(1) dan tidak mengeksekusi query N+1 per shift di Blade
        $this->assertLessThanOrEqual(
            16,
            $queryCount,
            "Query count ({$queryCount}) harus dibatasi O(1) dan tidak mengeksekusi query N+1 per shift di Blade"
        );
    }

    public function test_open_slot_claim_and_exchange_are_reachable_in_dynamic_modal_data(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch(['name' => 'Cabang Surabaya']);

        $openSlot = Shift::create([
            'user_id'    => null,
            'branch_id'  => $branch->id,
            'start_at'   => '2026-10-20 08:00:00',
            'end_at'     => '2026-10-20 16:00:00',
            'status'     => 'draft',
            'version'    => 1,
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();

        $shiftsMap = $response->viewData('shiftsMap');
        $this->assertNotNull($shiftsMap);
        $this->assertArrayHasKey($openSlot->id, $shiftsMap->all());

        $slotData = $shiftsMap[$openSlot->id];
        $this->assertTrue($slotData['isOpenSlot']);
        $this->assertTrue($slotData['canClaim']);
        $this->assertFalse($slotData['canExchange']);
    }
}

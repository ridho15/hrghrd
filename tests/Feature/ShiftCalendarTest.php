<?php

namespace Tests\Feature;

use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_only_sees_shifts_from_own_branch_in_calendar(): void
    {
        $branchA = $this->createBranch(['name' => 'Cabang Jakarta']);
        $branchB = $this->createBranch(['name' => 'Cabang Surabaya']);

        $managerA = $this->actingAsManager($branchA);
        $empA = $this->createUser(['branch_id' => $branchA->id, 'name' => 'Budi Jakarta']);
        $empB = $this->createUser(['branch_id' => $branchB->id, 'name' => 'Siti Surabaya']);

        $shiftA = $this->createShift($empA, $branchA, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at' => '2026-10-15 16:00:00',
        ]);
        $shiftB = $this->createShift($empB, $branchB, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at' => '2026-10-15 16:00:00',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertSee('Budi Jakarta');
        $response->assertDontSee('Siti Surabaya');
    }

    public function test_super_admin_can_filter_calendar_by_any_branch(): void
    {
        $this->actingAsAdmin();
        $branchA = $this->createBranch(['name' => 'Cabang Alpha']);
        $branchB = $this->createBranch(['name' => 'Cabang Beta']);

        $empA = $this->createUser(['branch_id' => $branchA->id, 'name' => 'Karyawan Alpha']);
        $empB = $this->createUser(['branch_id' => $branchB->id, 'name' => 'Karyawan Beta']);

        $this->createShift($empA, $branchA, ['start_at' => '2026-10-12 08:00:00', 'end_at' => '2026-10-12 16:00:00']);
        $this->createShift($empB, $branchB, ['start_at' => '2026-10-12 08:00:00', 'end_at' => '2026-10-12 16:00:00']);

        // Tanpa filter -> lihat semua
        $resAll = $this->get('/shifts/calendar?month=2026-10');
        $resAll->assertOk();
        $resAll->assertSee('Karyawan Alpha');
        $resAll->assertSee('Karyawan Beta');

        // Filter Cabang Alpha saja
        $resFilter = $this->get('/shifts/calendar?month=2026-10&branch_id=' . $branchA->id);
        $resFilter->assertOk();
        $resFilter->assertSee('Karyawan Alpha');
        $resFilter->assertDontSee('Karyawan Beta');
    }

    public function test_calendar_handles_invalid_or_extreme_month_parameters_gracefully(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staff Uji']);
        $this->createShift($emp, $branch, ['start_at' => now('Asia/Jakarta')->format('Y-m-d 09:00:00'), 'end_at' => now('Asia/Jakarta')->format('Y-m-d 17:00:00')]);

        // String acak
        $res1 = $this->get('/shifts/calendar?month=invalid-date-string');
        $res1->assertOk();

        // Bulan ekstrem / out of range
        $res2 = $this->get('/shifts/calendar?month=9999-99');
        $res2->assertOk();

        // Parameter kosong
        $res3 = $this->get('/shifts/calendar?month=');
        $res3->assertOk();
    }

    public function test_calendar_json_feed_returns_correct_event_payload(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Andi Feed']);

        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-20 09:00:00',
            'end_at' => '2026-10-20 17:00:00',
            'status' => 'approved',
        ]);

        $response = $this->getJson('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'month'   => '2026-10',
        ]);
        $response->assertJsonFragment([
            'id' => $shift->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_calendar_displays_correct_attendance_badges(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Bambang Hadir']);

        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-05 08:00:00',
            'end_at' => '2026-10-05 16:00:00',
            'status' => 'approved',
        ]);

        Attendance::create([
            'shift_id'   => $shift->id,
            'user_id'    => $emp->id,
            'checkin_at' => '2026-10-05 07:55:00',
            'checkout_at'=> '2026-10-05 16:05:00',
            'status'     => 'present',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertSee('Hadir');
    }
}

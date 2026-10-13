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

    public function test_calendar_displays_s1_and_s2_badges_and_legend(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Roster']);

        // Shift 1 (Pagi: 07:00)
        $this->createShift($emp, $branch, [
            'start_at' => '2026-10-06 07:00:00',
            'end_at'   => '2026-10-06 15:00:00',
            'status'   => 'approved',
        ]);

        // Shift 2 (Siang: 14:00)
        $this->createShift($emp, $branch, [
            'start_at' => '2026-10-07 14:00:00',
            'end_at'   => '2026-10-07 22:00:00',
            'status'   => 'approved',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertSee('S1');
        $response->assertSee('S2');
        $response->assertSee('Shift 1 (Pagi: Mulai &lt; 12:00)', false);
        $response->assertSee('Shift 2 (Siang: Mulai &ge; 12:00)', false);
    }

    public function test_calendar_displays_libur_badge_when_employee_filtered(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Libur']);

        // Hanya 1 shift di tanggal 10 Oktober
        $this->createShift($emp, $branch, [
            'start_at' => '2026-10-10 08:00:00',
            'end_at'   => '2026-10-10 16:00:00',
            'status'   => 'approved',
        ]);

        // Filter per user_id karyawan
        $response = $this->get('/shifts/calendar?month=2026-10&user_id=' . $emp->id);
        $response->assertOk();
        $response->assertSee('Libur');
    }

    public function test_calendar_attendance_mode_renders_summaries_and_stats(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);

        $emp1 = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Hadir']);
        $emp2 = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Terlambat']);
        $emp3 = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Alfa']);

        $shift1 = $this->createShift($emp1, $branch, [
            'start_at' => '2026-10-14 08:00:00',
            'end_at'   => '2026-10-14 16:00:00',
            'status'   => 'approved',
        ]);
        Attendance::create([
            'shift_id'   => $shift1->id,
            'user_id'    => $emp1->id,
            'checkin_at' => '2026-10-14 07:55:00',
            'checkout_at'=> '2026-10-14 16:00:00',
            'status'     => 'present',
        ]);

        $shift2 = $this->createShift($emp2, $branch, [
            'start_at' => '2026-10-14 08:00:00',
            'end_at'   => '2026-10-14 16:00:00',
            'status'   => 'approved',
        ]);
        Attendance::create([
            'shift_id'   => $shift2->id,
            'user_id'    => $emp2->id,
            'checkin_at' => '2026-10-14 08:25:00',
            'checkout_at'=> '2026-10-14 16:00:00',
            'status'     => 'late',
            'late_minutes' => 25,
            'late_units' => 1,
        ]);

        $shift3 = $this->createShift($emp3, $branch, [
            'start_at' => '2026-10-14 08:00:00',
            'end_at'   => '2026-10-14 16:00:00',
            'status'   => 'approved',
        ]);
        Attendance::create([
            'shift_id'   => $shift3->id,
            'user_id'    => $emp3->id,
            'status'     => 'absent',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10&mode=attendance');
        $response->assertOk();

        // Cek header dan switcher
        $response->assertSee('Kalender Tinjau Presensi');
        $response->assertSee('Total Shift Bulan Ini');
        $response->assertSee('Hadir Tepat Waktu');
        $response->assertSee('Terlambat Presensi');
        $response->assertSee('Alfa / Mangkir');

        // Cek komponen mobile render dengan baik
        $response->assertSee('id="mobile-cal-section"', false);
        $response->assertSee('id="mobile-agenda-cards-container"', false);
        $response->assertSee('id="mobile-list-section"', false);
        $response->assertSee('Kalender Mini');
        $response->assertSee('List Agenda');

        // Cek nama karyawan dan card partial
        $response->assertSee('Karyawan Hadir');
        $response->assertSee('Karyawan Terlambat');
        $response->assertSee('Karyawan Alfa');
    }

    public function test_calendar_filters_by_attendance_status(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);

        $emp1 = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staf Ontime']);
        $emp2 = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staf Telat']);

        $shift1 = $this->createShift($emp1, $branch, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at'   => '2026-10-15 16:00:00',
            'status'   => 'approved',
        ]);
        Attendance::create([
            'shift_id'   => $shift1->id,
            'user_id'    => $emp1->id,
            'checkin_at' => '2026-10-15 07:58:00',
            'status'     => 'present',
        ]);

        $shift2 = $this->createShift($emp2, $branch, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at'   => '2026-10-15 16:00:00',
            'status'   => 'approved',
        ]);
        Attendance::create([
            'shift_id'   => $shift2->id,
            'user_id'    => $emp2->id,
            'checkin_at' => '2026-10-15 08:30:00',
            'status'     => 'late',
            'late_minutes' => 30,
            'late_units' => 2,
        ]);

        // Filter status 'late'
        $resLate = $this->get('/shifts/calendar?month=2026-10&mode=attendance&attendance_status=late');
        $resLate->assertOk();
        $resLate->assertSee('modal-shift-' . $shift2->id);
        $resLate->assertDontSee('modal-shift-' . $shift1->id);

        // Filter status 'present'
        $resPresent = $this->get('/shifts/calendar?month=2026-10&mode=attendance&attendance_status=present');
        $resPresent->assertOk();
        $resPresent->assertSee('modal-shift-' . $shift1->id);
        $resPresent->assertDontSee('modal-shift-' . $shift2->id);
    }

    public function test_calendar_json_feed_includes_attendance_summary(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);

        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staf JSON Stat']);
        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-16 08:00:00',
            'end_at'   => '2026-10-16 16:00:00',
            'status'   => 'approved',
        ]);
        Attendance::create([
            'shift_id'   => $shift->id,
            'user_id'    => $emp->id,
            'checkin_at' => '2026-10-16 07:50:00',
            'checkout_at'=> '2026-10-16 16:00:00',
            'status'     => 'present',
        ]);

        $response = $this->getJson('/shifts/calendar?month=2026-10&mode=attendance');
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'month',
            'mode',
            'month_summary' => [
                'total_shifts',
                'total_present',
                'total_late',
                'total_absent',
                'total_on_duty',
                'total_exceptions',
            ],
            'attendance_summary_by_date',
            'data',
        ]);

        $this->assertEquals(1, $response->json('month_summary.total_present'));
    }

    public function test_calendar_mobile_selected_date_parameter(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);

        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staf Mobile Agenda']);
        $this->createShift($emp, $branch, [
            'start_at' => '2026-10-22 08:00:00',
            'end_at'   => '2026-10-22 16:00:00',
            'status'   => 'approved',
        ]);

        // Akses langsung tanggal tertentu via query param date
        $response = $this->get('/shifts/calendar?month=2026-10&date=2026-10-22');
        $response->assertOk();
        $response->assertSee('Staf Mobile Agenda');
        $response->assertSee('2026-10-22');
    }
}

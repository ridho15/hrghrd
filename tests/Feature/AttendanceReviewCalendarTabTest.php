<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReviewCalendarTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_review_defaults_to_calendar_tab_view(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);

        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Tab Kalender']);
        $shift = $this->createShift($emp, $branch, [
            'start_at' => now('Asia/Jakarta')->format('Y-m-d 08:00:00'),
            'end_at'   => now('Asia/Jakarta')->format('Y-m-d 16:00:00'),
            'status'   => 'approved',
        ]);
        Attendance::create([
            'shift_id'   => $shift->id,
            'user_id'    => $emp->id,
            'checkin_at' => now('Asia/Jakarta')->format('Y-m-d 07:55:00'),
            'status'     => 'present',
        ]);

        $response = $this->get('/attendance-review');
        $response->assertOk();

        // Verifikasi keberadaan Tab Switcher
        $response->assertSee('Kalender Presensi');
        $response->assertSee('Tabel & Log Presensi', false);

        // Verifikasi komponen Kalender Presensi aktif
        $response->assertSee('id="tab-calendar-container"', false);
        $response->assertSee('Total Shift Bulan Ini');
        $response->assertSee('Hadir Tepat Waktu');
        $response->assertSee('Terlambat Presensi');
        $response->assertSee('Alfa / Mangkir');

        // Verifikasi nama karyawan dan komponen mobile
        $response->assertSee('Karyawan Tab Kalender');
        $response->assertSee('id="mobile-cal-section"', false);
        $response->assertSee('id="mobile-agenda-cards-container"', false);
    }

    public function test_attendance_review_shows_pending_exceptions_alert_and_badge(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);

        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Kendala']);
        $shift = $this->createShift($emp, $branch, [
            'start_at' => now('Asia/Jakarta')->format('Y-m-d 08:00:00'),
            'end_at'   => now('Asia/Jakarta')->format('Y-m-d 16:00:00'),
        ]);

        AttendanceException::create([
            'user_id'      => $emp->id,
            'shift_id'     => $shift->id,
            'action'       => 'in',
            'claimed_time' => now('Asia/Jakarta')->format('H:i:s'),
            'reason'       => 'Kamera rusak saat scan',
            'status'       => 'pending',
        ]);

        $response = $this->get('/attendance-review?view=calendar');
        $response->assertOk();

        // Verifikasi banner peringatan kendala tertunda di tab kalender
        $response->assertSee('1 Tertunda');
        $response->assertSee('pengajuan kendala presensi yang menunggu verifikasi');
    }

    public function test_attendance_review_can_switch_to_table_tab_view(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);

        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Log Tabel']);
        $shift = $this->createShift($emp, $branch, [
            'start_at' => now('Asia/Jakarta')->format('Y-m-d 08:00:00'),
            'end_at'   => now('Asia/Jakarta')->format('Y-m-d 16:00:00'),
        ]);
        Attendance::create([
            'shift_id'   => $shift->id,
            'user_id'    => $emp->id,
            'checkin_at' => now('Asia/Jakarta')->format('Y-m-d 07:50:00'),
            'status'     => 'present',
        ]);

        $response = $this->get('/attendance-review?view=table');
        $response->assertOk();

        // Verifikasi konten tabel hadir
        $response->assertSee('id="tab-table-container"', false);
        $response->assertSee('Pengecualian Menunggu Keputusan');
        $response->assertSee('Riwayat Presensi & Koreksi', false);
        $response->assertSee('Karyawan Log Tabel');
    }

    public function test_attendance_review_calendar_filters_by_attendance_status(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);

        $emp1 = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staf Tepat Waktu']);
        $emp2 = $this->createUser(['branch_id' => $branch->id, 'name' => 'Staf Terlambat Masuk']);

        $shift1 = $this->createShift($emp1, $branch, [
            'start_at' => now('Asia/Jakarta')->format('Y-m-d 08:00:00'),
            'end_at'   => now('Asia/Jakarta')->format('Y-m-d 16:00:00'),
            'status'   => 'approved',
        ]);
        Attendance::create([
            'shift_id'   => $shift1->id,
            'user_id'    => $emp1->id,
            'checkin_at' => now('Asia/Jakarta')->format('Y-m-d 07:55:00'),
            'status'     => 'present',
        ]);

        $shift2 = $this->createShift($emp2, $branch, [
            'start_at' => now('Asia/Jakarta')->format('Y-m-d 08:00:00'),
            'end_at'   => now('Asia/Jakarta')->format('Y-m-d 16:00:00'),
            'status'   => 'approved',
        ]);
        Attendance::create([
            'shift_id'     => $shift2->id,
            'user_id'      => $emp2->id,
            'checkin_at'   => now('Asia/Jakarta')->format('Y-m-d 08:35:00'),
            'status'       => 'late',
            'late_minutes' => 35,
            'late_units'   => 2,
        ]);

        // Filter status 'late' di halaman attendance-review
        $resLate = $this->get('/attendance-review?view=calendar&attendance_status=late');
        $resLate->assertOk();
        $resLate->assertSee('modal-shift-' . $shift2->id);
        $resLate->assertDontSee('modal-shift-' . $shift1->id);

        // Filter status 'present'
        $resPresent = $this->get('/attendance-review?view=calendar&attendance_status=present');
        $resPresent->assertOk();
        $resPresent->assertSee('modal-shift-' . $shift1->id);
        $resPresent->assertDontSee('modal-shift-' . $shift2->id);
    }

    public function test_sidebar_contains_link_to_attendance_review_calendar(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);

        $response = $this->get(route('home'));
        $response->assertOk();

        // Sidebar harus mengarahkan ke review dengan view=calendar
        $expectedUrl = route('attendance.review', ['view' => 'calendar']);
        $response->assertSee($expectedUrl);
        $response->assertSee('Tinjau Absensi');
    }
}

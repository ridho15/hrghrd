<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarAttendanceUnhappyPathTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Unhappy 1: Pengguna tanpa autentikasi (guest) tidak boleh mengakses kalender.
     */
    public function test_guest_cannot_access_shift_calendar_or_attendance_review(): void
    {
        $resCalendar = $this->get('/shifts/calendar');
        $resCalendar->assertRedirect('/login');

        $resReview = $this->get('/attendance-review');
        $resReview->assertRedirect('/login');
    }

    /**
     * Unhappy 2: Karyawan lupa checkout (shift sudah lewat, check-in ada, checkout null).
     * Sistem TIDAK boleh menandainya sebagai 'Presensi Tuntas / Biru Pekat' (bg-blue-600).
     */
    public function test_unhappy_missed_checkout_does_not_show_deep_blue_completed(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Lupa Pulang']);

        // Shift kemarin (sudah lewat jam kerja)
        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-10 08:00:00',
            'end_at'   => '2026-10-10 16:00:00',
            'status'   => 'approved',
        ]);

        // Checkin ada, checkout TIDAK ADA
        Attendance::create([
            'shift_id'   => $shift->id,
            'user_id'    => $emp->id,
            'checkin_at' => '2026-10-10 07:55:00',
            'checkout_at'=> null,
            'status'     => 'present',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertSee('Karyawan Lupa Pulang');

        // Tidak boleh menampilkan biru pekat tuntas (bg-blue-600) untuk shift ini
        // Karena belum checkout, tampil sebagai biru aktif atau sedang dinas (bg-sky-100)
        $response->assertSee('bg-sky-100');
        $response->assertSee('Masuk: 07:55');
    }

    /**
     * Unhappy 3: Alfa / Mangkir (Shift di masa lalu tanpa scan presensi sama sekali).
     * Harus tampil dengan nuansa Merah (Rose) dan badge Alfa, BUKAN biru.
     */
    public function test_unhappy_alfa_shift_shows_rose_chip_and_alfa_badge(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Mangkir']);

        // Shift di masa lalu tanpa attendance record
        $this->createShift($emp, $branch, [
            'start_at' => '2026-10-08 08:00:00',
            'end_at'   => '2026-10-08 16:00:00',
            'status'   => 'approved',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertSee('Karyawan Mangkir');
        $response->assertSee('bg-rose-50');
        $response->assertSee('Alfa');
    }

    /**
     * Unhappy 4: Anomali Data: Ada waktu checkout tetapi checkin bernilai null.
     * Tidak boleh dianggap tuntas (bg-blue-600).
     */
    public function test_unhappy_anomaly_checkout_without_checkin_is_not_treated_as_completed(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Anomali']);

        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-14 08:00:00',
            'end_at'   => '2026-10-14 16:00:00',
            'status'   => 'approved',
        ]);

        // Glitch data: checkout terisi, checkin null
        Attendance::create([
            'shift_id'   => $shift->id,
            'user_id'    => $emp->id,
            'checkin_at' => null,
            'checkout_at'=> '2026-10-14 16:00:00',
            'status'     => 'present',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertSee('Karyawan Anomali');

        // Pastikan chip shift spesifik ini tidak berstatus Hadir tuntas (bg-blue-600)
        $this->assertStringNotContainsString(
            'data-open-modal="modal-shift-' . $shift->id . '" title="Hadir" class="w-full text-left p-1.5 rounded-lg border bg-blue-600',
            $response->getContent()
        );
    }

    /**
     * Unhappy 5: Shift berstatus Draf (belum disetujui / pending approval).
     * Tidak boleh diberi warna jadwal sah (bg-sky-50), melainkan warna netral draf (bg-slate-50).
     */
    public function test_unhappy_draft_shift_shows_neutral_draft_styling(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Shift Draf']);

        $this->createShift($emp, $branch, [
            'start_at' => '2026-10-25 08:00:00',
            'end_at'   => '2026-10-25 16:00:00',
            'status'   => 'draft',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10');
        $response->assertOk();
        $response->assertSee('Karyawan Shift Draf');
        $response->assertSee('bg-slate-50');
        $response->assertSee('Draf');
    }

    /**
     * Unhappy 6: Parameter query string rusak, salah format, atau ekstrem.
     * Aplikasi harus merespons secara aman (graceful degradation) tanpa error 500.
     */
    public function test_unhappy_extreme_and_malformed_calendar_parameters_do_not_crash(): void
    {
        $this->actingAsAdmin();

        $urls = [
            '/shifts/calendar?month=invalid-date-format',
            '/shifts/calendar?month=9999-99',
            '/shifts/calendar?month=&user_id=abc&status=unknown',
            '/attendance-review?view=invalid_view&month=undefined',
            '/attendance-review?view=calendar&attendance_status=non_existent_status',
            '/attendance-review?view=calendar&branch_id=-999&user_id=-1',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            $response->assertOk();
        }
    }

    /**
     * Unhappy 7: Filter karyawan atau cabang tanpa data (Empty state).
     * Kalender harus tetap dirender dengan rapi tanpa error runtime.
     */
    public function test_unhappy_empty_filter_results_render_cleanly(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/attendance-review?view=calendar&user_id=999999&branch_id=999999');
        $response->assertOk();
        $response->assertSee('Kalender Presensi');
        $response->assertSee('Total Shift Bulan Ini');
    }

    /**
     * Happy + Edge: Karyawan terlambat yang menyelesaikan check-in dan check-out.
     * Harus menampilkan Biru Pekat (bg-blue-600) dengan indikator selesai terlambat.
     */
    public function test_late_employee_with_completed_checkout_shows_deep_blue_with_late_title(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $emp = $this->createUser(['branch_id' => $branch->id, 'name' => 'Karyawan Telat Tapi Pulang']);

        $shift = $this->createShift($emp, $branch, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at'   => '2026-10-15 16:00:00',
            'status'   => 'approved',
        ]);

        Attendance::create([
            'shift_id'     => $shift->id,
            'user_id'      => $emp->id,
            'checkin_at'   => '2026-10-15 08:30:00',
            'checkout_at'  => '2026-10-15 16:05:00',
            'status'       => 'late',
            'late_minutes' => 30,
            'late_units'   => 2,
        ]);

        // Cek di /shifts/calendar
        $resCalendar = $this->get('/shifts/calendar?month=2026-10');
        $resCalendar->assertOk();
        $resCalendar->assertSee('bg-blue-600');
        $resCalendar->assertSee('Terlambat (Selesai)');
        $resCalendar->assertSee('08:30-16:05');

        // Cek di /attendance-review?view=calendar
        $resReview = $this->get('/attendance-review?view=calendar&month=2026-10');
        $resReview->assertOk();
        $resReview->assertSee('bg-blue-600');
        $resReview->assertSee('Terlambat (Selesai)');
        $resReview->assertSee('08:30-16:05');
    }

    /**
     * Security Boundary: Manajer Cabang A tidak boleh melihat jadwal / presensi Cabang B di kalender tinjau presensi.
     */
    public function test_manager_cannot_see_shifts_from_other_branch_in_attendance_review(): void
    {
        $branchA = $this->createBranch(['name' => 'Cabang Medan']);
        $branchB = $this->createBranch(['name' => 'Cabang Bandung']);

        $managerA = $this->actingAsManager($branchA);
        $empA = $this->createUser(['branch_id' => $branchA->id, 'name' => 'Staf Medan']);
        $empB = $this->createUser(['branch_id' => $branchB->id, 'name' => 'Staf Bandung']);

        $shiftA = $this->createShift($empA, $branchA, ['start_at' => '2026-10-16 08:00:00', 'end_at' => '2026-10-16 16:00:00']);
        $shiftB = $this->createShift($empB, $branchB, ['start_at' => '2026-10-16 08:00:00', 'end_at' => '2026-10-16 16:00:00']);

        $response = $this->get('/attendance-review?view=calendar&month=2026-10');
        $response->assertOk();
        $response->assertSee('Staf Medan');
        $response->assertDontSee('Staf Bandung');
    }
}

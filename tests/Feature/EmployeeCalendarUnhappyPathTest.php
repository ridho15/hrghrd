<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCalendarUnhappyPathTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Unhappy 1: Karyawan mencoba memanipulasi parameter ?branch_id ke cabang lain.
     * Sistem HARUS mengunci query ke cabang milik karyawan sendiri dan tidak boleh membocorkan data cabang lain.
     */
    public function test_unhappy_employee_cannot_view_other_branches_via_tampered_branch_id(): void
    {
        $branch1 = $this->createBranch(['name' => 'Cabang Jakarta']);
        $branch2 = $this->createBranch(['name' => 'Cabang Surabaya']);

        $emp1 = $this->createUser(['branch_id' => $branch1->id, 'role' => 'employee', 'name' => 'Karyawan Jakarta']);
        $emp2 = $this->createUser(['branch_id' => $branch2->id, 'role' => 'employee', 'name' => 'Karyawan Surabaya']);

        $shift1 = $this->createShift($emp1, $branch1, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at'   => '2026-10-15 16:00:00',
        ]);

        $shift2 = $this->createShift($emp2, $branch2, [
            'start_at' => '2026-10-15 08:00:00',
            'end_at'   => '2026-10-15 16:00:00',
        ]);

        // Login sebagai Karyawan 1 (Jakarta), lalu sengaja mengirim parameter ?branch_id milik Cabang Surabaya
        $this->actingAs($emp1);
        $response = $this->get('/shifts/calendar?month=2026-10&branch_id=' . $branch2->id);

        $response->assertOk();
        $response->assertSee('modal-shift-' . $shift1->id);
        // Shift milik Cabang Surabaya TIDAK BOLEH bocor
        $response->assertDontSee('modal-shift-' . $shift2->id);

        $viewShifts = $response->viewData('shifts');
        $this->assertTrue($viewShifts->contains('id', $shift1->id));
        $this->assertFalse($viewShifts->contains('id', $shift2->id));
    }

    /**
     * Unhappy 2: Karyawan mencoba memalsukan (spoofing) ?user_id karyawan lain saat dalam scope=me.
     * Sistem HARUS mengabaikan user_id asing dan tetap mengunci ke akun karyawan yang sedang login.
     */
    public function test_unhappy_employee_cannot_spoof_another_user_in_default_scope_me(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee', 'name' => 'Budi A']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee', 'name' => 'Bambang B']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-20 08:00:00',
            'end_at'   => '2026-10-20 16:00:00',
        ]);

        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => '2026-10-20 08:00:00',
            'end_at'   => '2026-10-20 16:00:00',
        ]);

        // Login sebagai Budi A, mencoba request kalender dengan ?scope=me&user_id={Bambang B}
        $this->actingAs($empA);
        $response = $this->get('/shifts/calendar?month=2026-10&scope=me&user_id=' . $empB->id);

        $response->assertOk();
        $response->assertSee('modal-shift-' . $shiftA->id);
        $response->assertDontSee('modal-shift-' . $shiftB->id);

        $viewShifts = $response->viewData('shifts');
        $this->assertTrue($viewShifts->contains('id', $shiftA->id));
        $this->assertFalse($viewShifts->contains('id', $shiftB->id));
    }

    /**
     * Unhappy 3: Karyawan mencoba memfilter ke karyawan dari cabang lain saat ?scope=branch.
     * Filter branch_id tetap membatasi ke cabang sendiri, sehingga tidak ada data cabang luar yang bocor.
     */
    public function test_unhappy_employee_cannot_view_cross_branch_colleague_in_scope_branch(): void
    {
        $branch1 = $this->createBranch();
        $branch2 = $this->createBranch();

        $emp1 = $this->createUser(['branch_id' => $branch1->id, 'role' => 'employee']);
        $empForeign = $this->createUser(['branch_id' => $branch2->id, 'role' => 'employee']);

        $shiftForeign = $this->createShift($empForeign, $branch2, [
            'start_at' => '2026-10-18 08:00:00',
            'end_at'   => '2026-10-18 16:00:00',
        ]);

        $this->actingAs($emp1);
        $response = $this->get('/shifts/calendar?month=2026-10&scope=branch&user_id=' . $empForeign->id);

        $response->assertOk();
        $response->assertDontSee('modal-shift-' . $shiftForeign->id);

        $viewShifts = $response->viewData('shifts');
        $this->assertCount(0, $viewShifts);
    }

    /**
     * Unhappy 4: Parameter bulan dan tanggal yang rusak atau tidak valid (malformed string).
     * Sistem tidak boleh crash / 500 error, melainkan fallback secara aman ke bulan berjalan.
     */
    public function test_unhappy_invalid_or_malformed_month_and_date_parameters_fallback_gracefully(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        // Uji berbagai format parameter yang rusak
        $malformedUrls = [
            '/shifts/calendar?month=invalid-format',
            '/shifts/calendar?month=99999999',
            '/shifts/calendar?month=2026-15',
            '/shifts/calendar?month=<script>alert(1)</script>',
            '/shifts/calendar?month=2026-10&date=not-a-real-date',
            '/shifts/calendar?month=2026-10&date=2026/10/15',
        ];

        foreach ($malformedUrls as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertSee('Kalender');
        }
    }

    /**
     * Unhappy 5: Karyawan tanpa jadwal sama sekali pada bulan tertentu (bulan kosong).
     * Tidak boleh menimbulkan notice/warning PHP, metrik bernilai 0 dan tampil ramah pengguna.
     */
    public function test_unhappy_employee_empty_month_renders_zero_metrics_without_error(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        // Akses bulan yang sama sekali tidak memiliki shift
        $response = $this->get('/shifts/calendar?month=2024-01');
        $response->assertOk();

        $monthSummary = $response->viewData('monthSummary');
        $this->assertEquals(0, $monthSummary['total_shifts']);
        $this->assertEquals(0, $monthSummary['total_present']);
        $this->assertEquals(0, $monthSummary['total_late']);
        $this->assertEquals(0, $monthSummary['total_absent']);

        $viewShifts = $response->viewData('shifts');
        $this->assertCount(0, $viewShifts);
    }

    /**
     * Unhappy 6: Karyawan mencoba mengajukan pertukaran untuk shift yang BUKAN miliknya.
     * Harus ditolak dengan status 403 Forbidden.
     */
    public function test_unhappy_employee_cannot_request_exchange_for_shift_they_do_not_own(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee']);
        $empC = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee']);

        // Shift milik Karyawan B
        $shiftB = $this->createShift($empB, $branch, [
            'start_at' => '2026-10-25 08:00:00',
            'end_at'   => '2026-10-25 16:00:00',
        ]);

        // Karyawan A mencoba mengajukan penggantian atas shift B
        $this->actingAs($empA);
        $response = $this->post('/shifts/' . $shiftB->id . '/exchange', [
            'type'           => 'replacement',
            'replacement_id' => $empC->id,
            'reason'         => 'Mencoba membajak shift orang lain tanpa izin',
        ]);

        $response->assertForbidden();
    }

    /**
     * Unhappy 7: Karyawan mencoba menukar shift yang sudah memiliki rekaman absensi.
     * Harus ditolak dengan status 409 Conflict.
     */
    public function test_unhappy_employee_cannot_exchange_shift_with_existing_attendance(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-20 08:00:00',
            'end_at'   => '2026-10-20 16:00:00',
        ]);

        // Shift sudah ada checkin
        Attendance::create([
            'shift_id'   => $shiftA->id,
            'user_id'    => $empA->id,
            'checkin_at' => '2026-10-20 08:00:00',
            'status'     => 'present',
        ]);

        $this->actingAs($empA);
        $response = $this->post('/shifts/' . $shiftA->id . '/exchange', [
            'type'           => 'replacement',
            'replacement_id' => $empB->id,
            'reason'         => 'Ingin menukar padahal sudah check-in',
        ]);

        $response->assertStatus(403);

        $manager = $this->createUser(['branch_id' => $branch->id, 'role' => 'manager']);
        $this->actingAs($manager);
        $resMgr = $this->post('/shifts/' . $shiftA->id . '/exchange', [
            'type'           => 'replacement',
            'replacement_id' => $empB->id,
            'reason'         => 'Ingin menukar padahal sudah check-in',
        ]);
        $resMgr->assertStatus(409);
    }

    /**
     * Unhappy 8: Karyawan mencoba menukar jadwal dengan diri sendiri.
     * Harus ditolak dengan status 403 (karyawan tidak berhak) dan 422 jika diproses manajer.
     */
    public function test_unhappy_employee_cannot_exchange_shift_with_self(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee']);

        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-28 08:00:00',
            'end_at'   => '2026-10-28 16:00:00',
        ]);

        $this->actingAs($empA);
        $response = $this->post('/shifts/' . $shiftA->id . '/exchange', [
            'type'           => 'replacement',
            'replacement_id' => $empA->id, // Diri sendiri
            'reason'         => 'Menukar dengan diri sendiri',
        ]);

        $response->assertStatus(403);

        $manager = $this->createUser(['branch_id' => $branch->id, 'role' => 'manager']);
        $this->actingAs($manager);
        $resMgr = $this->post('/shifts/' . $shiftA->id . '/exchange', [
            'type'           => 'replacement',
            'replacement_id' => $empA->id,
            'reason'         => 'Menukar dengan diri sendiri',
        ]);
        $resMgr->assertStatus(422);
    }

    /**
     * Unhappy 9: Karyawan mencoba menukar jadwal dengan rekan dari cabang yang berbeda.
     * Harus ditolak dengan status 403 (karyawan tidak berhak) dan 422 jika diproses manajer.
     */
    public function test_unhappy_employee_cannot_exchange_shift_with_colleague_from_different_branch(): void
    {
        $branchA = $this->createBranch(['name' => 'Cabang Alpha']);
        $branchB = $this->createBranch(['name' => 'Cabang Beta']);

        $empA = $this->createUser(['branch_id' => $branchA->id, 'role' => 'employee']);
        $empDifferentBranch = $this->createUser(['branch_id' => $branchB->id, 'role' => 'employee']);

        $shiftA = $this->createShift($empA, $branchA, [
            'start_at' => '2026-10-29 08:00:00',
            'end_at'   => '2026-10-29 16:00:00',
        ]);

        $this->actingAs($empA);
        $response = $this->post('/shifts/' . $shiftA->id . '/exchange', [
            'type'           => 'replacement',
            'replacement_id' => $empDifferentBranch->id,
            'reason'         => 'Menukar ke cabang lain yang berbeda',
        ]);

        $response->assertStatus(403);

        $manager = $this->createUser(['branch_id' => $branchA->id, 'role' => 'manager']);
        $this->actingAs($manager);
        $resMgr = $this->post('/shifts/' . $shiftA->id . '/exchange', [
            'type'           => 'replacement',
            'replacement_id' => $empDifferentBranch->id,
            'reason'         => 'Menukar ke cabang lain yang berbeda',
        ]);
        $resMgr->assertStatus(422);
    }

    /**
     * Unhappy 10: Karyawan mencoba menukar ke rekan yang sudah memiliki jadwal bentrok (overlapping shift).
     * Harus ditolak dengan status 403 (karyawan tidak berhak) dan 409 jika diproses manajer.
     */
    public function test_unhappy_employee_cannot_exchange_shift_with_overlapping_schedule(): void
    {
        $branch = $this->createBranch();
        $empA = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee']);
        $empB = $this->createUser(['branch_id' => $branch->id, 'role' => 'employee']);

        // Shift A: 08:00 s/d 16:00
        $shiftA = $this->createShift($empA, $branch, [
            'start_at' => '2026-10-30 08:00:00',
            'end_at'   => '2026-10-30 16:00:00',
        ]);

        // Shift Rekan B: 12:00 s/d 20:00 (bentrok sebagian dari jam 12:00 s/d 16:00)
        $this->createShift($empB, $branch, [
            'start_at' => '2026-10-30 12:00:00',
            'end_at'   => '2026-10-30 20:00:00',
        ]);

        $this->actingAs($empA);
        $response = $this->post('/shifts/' . $shiftA->id . '/exchange', [
            'type'           => 'replacement',
            'replacement_id' => $empB->id,
            'reason'         => 'Menukar ke rekan yang jamnya tumpang tindih',
        ]);

        $response->assertStatus(403);

        $manager = $this->createUser(['branch_id' => $branch->id, 'role' => 'manager']);
        $this->actingAs($manager);
        $resMgr = $this->post('/shifts/' . $shiftA->id . '/exchange', [
            'type'           => 'replacement',
            'replacement_id' => $empB->id,
            'reason'         => 'Menukar ke rekan yang jamnya tumpang tindih',
        ]);
        $resMgr->assertStatus(409);
    }

    /**
     * Unhappy 11: Karyawan dilarang keras mengakses endpoint manajerial CRUD shift dan audit review.
     * Semua endpoint ini HARUS merespons 403 Forbidden.
     */
    public function test_unhappy_employee_forbidden_from_all_managerial_shift_actions(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);
        $shift = $this->createShift($employee, $branch);

        // 1. Index tabel shift
        $this->get('/shifts')->assertForbidden();

        // 2. Formulir buat shift
        $this->get('/shifts/create')->assertForbidden();

        // 3. Simpan shift baru
        $this->post('/shifts', [
            'user_id'   => $employee->id,
            'branch_id' => $branch->id,
            'start_at'  => '2026-10-21 08:00:00',
            'end_at'    => '2026-10-21 16:00:00',
        ])->assertForbidden();

        // 4. Detail shift manajerial
        $this->get('/shifts/' . $shift->id)->assertForbidden();

        // 5. Formulir edit shift
        $this->get('/shifts/' . $shift->id . '/edit')->assertForbidden();

        // 6. Update shift
        $this->post('/shifts/' . $shift->id . '/update', [
            'start_at'  => '2026-10-21 09:00:00',
            'end_at'    => '2026-10-21 17:00:00',
        ])->assertForbidden();

        // 7. Approve shift
        $this->post('/shifts/' . $shift->id . '/approve')->assertForbidden();

        // 8. Manual attendance injection
        $this->post('/shifts/' . $shift->id . '/attendance-manual', [
            'action' => 'in',
            'time'   => '08:00:00',
        ])->assertForbidden();

        // 9. Reassign shift oleh manajer
        $this->post('/shifts/' . $shift->id . '/reassign', [
            'replacement_id' => $employee->id,
        ])->assertForbidden();

        // 10. Hapus shift
        $this->post('/shifts/' . $shift->id . '/delete')->assertForbidden();
        $this->delete('/shifts/' . $shift->id)->assertForbidden();

        // 11. Halaman tinjau absensi manajerial
        $this->get('/attendance-review')->assertForbidden();
    }

    /**
     * Unhappy 12: Shift masa lalu yang terlewat tanpa presensi strictly dihitung sebagai Alfa (Mangkir).
     * Tidak boleh dihitung sebagai hadir, dan chip berwarna rose (merah muda).
     */
    public function test_unhappy_employee_past_unattended_shift_strictly_shows_as_alfa_not_present(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        // Shift 5 hari lalu yang tidak pernah di-scan
        $this->createShift($employee, $branch, [
            'start_at' => '2026-10-05 08:00:00',
            'end_at'   => '2026-10-05 16:00:00',
            'status'   => 'approved',
        ]);

        $response = $this->get('/shifts/calendar?month=2026-10&mode=attendance');
        $response->assertOk();

        $monthSummary = $response->viewData('monthSummary');
        $this->assertEquals(1, $monthSummary['total_shifts']);
        $this->assertEquals(0, $monthSummary['total_present']);
        $this->assertEquals(1, $monthSummary['total_absent']);

        // Verifikasi chip styling Alfa
        $response->assertSee('bg-rose-50');
        $response->assertSee('Alfa');
    }
}

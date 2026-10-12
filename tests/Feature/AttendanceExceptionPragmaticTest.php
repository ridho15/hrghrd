<?php

namespace Tests\Feature;

use App\Models\AttendanceException;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceExceptionPragmaticTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Memastikan karyawan dapat mengajukan pengecualian untuk shift lampau (7 hari terakhir)
     * disertai jam kedatangan/kepulangan riil (claimed_time).
     */
    public function test_employee_can_submit_exception_for_past_shift_with_claimed_time(): void
    {
        $branch = $this->createBranch(['name' => 'Cabang Sukajadi']);
        $employee = $this->actingAsEmployee($branch);

        $pastShiftDate = now('Asia/Jakarta')->subDays(2);
        $shift = $this->createShift($employee, $branch, [
            'start_at' => $pastShiftDate->copy()->setTime(8, 0, 0),
            'end_at' => $pastShiftDate->copy()->setTime(17, 0, 0),
            'status' => 'approved',
        ]);

        $response = $this->post(route('attendance.exception', $shift->id), [
            'action' => 'in',
            'claimed_time' => '08:05',
            'reason' => 'Sinyal handphone hilang di basement toko, tidak bisa scan QR kiosk saat datang.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_exceptions', [
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'action' => 'in',
            'claimed_time' => '08:05',
            'status' => 'pending',
            'reason' => 'Sinyal handphone hilang di basement toko, tidak bisa scan QR kiosk saat datang.',
        ]);
    }

    /**
     * Memastikan halaman attendance.today menampilkan riwayat shift lampau (susulan)
     * dan widget status permohonan pengecualian karyawan secara transparan.
     */
    public function test_employee_views_recent_shifts_and_active_exception_status_on_today_page(): void
    {
        $branch = $this->createBranch(['name' => 'Cabang Dago']);
        $employee = $this->actingAsEmployee($branch);

        // Shift 3 hari lalu
        $pastDate = now('Asia/Jakarta')->subDays(3);
        $pastShift = $this->createShift($employee, $branch, [
            'start_at' => $pastDate->copy()->setTime(9, 0, 0),
            'end_at' => $pastDate->copy()->setTime(18, 0, 0),
            'status' => 'approved',
        ]);

        // Buat pengecualian aktif
        $exception = AttendanceException::create([
            'shift_id' => $pastShift->id,
            'user_id' => $employee->id,
            'action' => 'in',
            'claimed_time' => '09:02:00',
            'reason' => 'GPS error radius 500m saat hujan lebat.',
            'status' => 'pending',
        ]);

        $response = $this->get(route('attendance.today'));

        $response->assertOk();
        $response->assertViewIs('attendance-today');
        $response->assertViewHas('recentShifts');
        $response->assertViewHas('myExceptions');
        $response->assertSee('Status Permohonan Pengecualian Saya');
        $response->assertSee('GPS error radius 500m saat hujan lebat.');
        $response->assertSee('Menunggu Review');
        $response->assertSee('Riwayat Jadwal Shift & Presensi (7 Hari Terakhir)', false);
    }

    /**
     * Memastikan dashboard karyawan memuat widget transparansi status pengecualian aktif
     * dengan catatan atasan / reviewer saat sudah diproses.
     */
    public function test_employee_sees_active_exception_widget_on_dashboard(): void
    {
        $branch = $this->createBranch(['name' => 'Cabang Riau']);
        $employee = $this->actingAsEmployee($branch);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id, 'name' => 'Manager Budi']);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => now('Asia/Jakarta')->subDays(1)->setTime(8, 0, 0),
            'end_at' => now('Asia/Jakarta')->subDays(1)->setTime(17, 0, 0),
            'status' => 'approved',
        ]);

        $exception = AttendanceException::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'action' => 'in',
            'claimed_time' => '08:00:00',
            'reason' => 'Kamera error tidak bisa membuka scanner.',
            'status' => 'approved',
            'reviewed_by' => $manager->id,
            'review_note' => 'Dikonfirmasi hadir oleh supervisor shift pagi.',
            'reviewed_at' => now('Asia/Jakarta'),
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertViewIs('dashboard');
        $response->assertViewHas('myExceptions');
        $response->assertSee('Status Kendala Presensi');
        $response->assertSee('Disetujui');
        $response->assertSee('Dikonfirmasi hadir oleh supervisor shift pagi.');
    }

    /**
     * Memastikan banner dashboard manager menampilkan counter kendala presensi
     * dan tombol direct link menuju route review kendala presensi.
     */
    public function test_manager_sees_pending_exception_count_and_direct_review_button_on_dashboard(): void
    {
        $branch = $this->createBranch(['name' => 'Cabang Buah Batu']);
        $manager = $this->actingAsManager($branch);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => now('Asia/Jakarta')->setTime(8, 0, 0),
            'end_at' => now('Asia/Jakarta')->setTime(17, 0, 0),
            'status' => 'approved',
        ]);

        AttendanceException::create([
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            'action' => 'in',
            'claimed_time' => '08:05:00',
            'reason' => 'Kendala GPS jumping pada jam masuk.',
            'status' => 'pending',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Tugas Menunggu Persetujuan Cabang');
        $response->assertSee('Tinjau Kendala Presensi');
        $response->assertSee(route('attendance.review'));
    }

    /**
     * Memastikan sistem menolak pengajuan ganda (duplicate) jika masih berstatus pending.
     */
    public function test_prevents_duplicate_pending_exception_submission(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => now('Asia/Jakarta')->subDay()->setTime(9, 0, 0),
            'end_at' => now('Asia/Jakarta')->subDay()->setTime(18, 0, 0),
            'status' => 'approved',
        ]);

        // Pengajuan pertama berhasil
        $res1 = $this->post(route('attendance.exception', $shift->id), [
            'action' => 'in',
            'claimed_time' => '09:00',
            'reason' => 'Pengajuan awal kendala presensi pertama kali.',
        ]);
        $res1->assertRedirect();

        // Pengajuan kedua untuk aksi yang sama sementara masih pending harus ditolak (409)
        $res2 = $this->post(route('attendance.exception', $shift->id), [
            'action' => 'in',
            'claimed_time' => '09:00',
            'reason' => 'Mencoba mengirim ulang alasan yang sama.',
        ]);
        $res2->assertStatus(409);
    }
}

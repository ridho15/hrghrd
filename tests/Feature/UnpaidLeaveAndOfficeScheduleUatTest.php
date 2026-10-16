<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnpaidLeaveAndOfficeScheduleUatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Happy Path: Karyawan dengan sisa cuti 0 berhasil mengajukan Cuti Tanpa Gaji (unpaid_leave).
     */
    public function test_unpaid_leave_submission_happy_path_with_zero_quota(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'annual_leave_quota' => 0,
        ]);

        $this->actingAs($employee, 'sanctum');

        $startDate = Carbon::now('Asia/Jakarta')->addDays(3)->toDateString();
        $endDate = Carbon::now('Asia/Jakarta')->addDays(4)->toDateString();

        $response = $this->postJson('/api/v1/leave', [
            'type' => 'unpaid_leave',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Urusan mendesak tanpa gaji',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $employee->id,
            'type' => 'unpaid_leave',
            'status' => 'pending',
        ]);
    }

    /**
     * 2. Non-Happy Path: Pengajuan cuti tahunan biasa (leave) DITOLAK jika kuota cuti tahunan habis (0).
     */
    public function test_annual_leave_submission_blocked_when_quota_exhausted(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'annual_leave_quota' => 0,
        ]);

        $this->actingAs($employee, 'sanctum');

        $startDate = Carbon::now('Asia/Jakarta')->addDays(5)->toDateString();
        $endDate = Carbon::now('Asia/Jakarta')->addDays(6)->toDateString();

        $response = $this->postJson('/api/v1/leave', [
            'type' => 'leave',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Mencoba ambil cuti tahunan padahal kuota 0',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Pengajuan cuti tahunan tidak dapat diproses', $response->json('message'));
    }

    /**
     * 3. Non-Happy Path: Validasi tanggal (end_date mendahului start_date) ditolak 422.
     */
    public function test_unpaid_leave_rejects_end_date_before_start_date(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $this->actingAs($employee, 'sanctum');

        $startDate = Carbon::now('Asia/Jakarta')->addDays(5)->toDateString();
        $endDate = Carbon::now('Asia/Jakarta')->addDays(3)->toDateString(); // Sebelum startDate

        $response = $this->postJson('/api/v1/leave', [
            'type' => 'unpaid_leave',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Tanggal terbalik',
        ]);

        $response->assertStatus(422);
    }

    /**
     * 4. Non-Happy Path: Tipe cuti tidak valid ditolak 422.
     */
    public function test_unpaid_leave_rejects_invalid_type(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/leave', [
            'type' => 'invalid_unpaid_custom',
            'start_date' => Carbon::now('Asia/Jakarta')->addDays(2)->toDateString(),
            'end_date' => Carbon::now('Asia/Jakarta')->addDays(3)->toDateString(),
            'reason' => 'Tipe ilegal',
        ]);

        $response->assertStatus(422);
    }

    /**
     * 5. Non-Happy Path: Karyawan dilarang mengajukan tipe day_off secara mandiri (403).
     */
    public function test_employee_forbidden_from_submitting_day_off_directly(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $this->actingAs($employee, 'sanctum');

        $response = $this->postJson('/api/v1/leave', [
            'type' => 'day_off',
            'start_date' => Carbon::now('Asia/Jakarta')->addDays(2)->toDateString(),
            'end_date' => Carbon::now('Asia/Jakarta')->addDays(2)->toDateString(),
            'reason' => 'Mencoba manipulasi day off mandiri',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 6. Happy Path: Karyawan office_hours di hari kerja (Rabu) otomatis mendapatkan shift kantor saat buka halaman presensi.
     */
    public function test_office_hours_auto_provisions_on_weekdays_and_allows_web_today_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 08:30:00', 'Asia/Jakarta')); // Rabu

        $branch = $this->createBranch();
        $employeeOffice = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'schedule_type' => 'office_hours',
        ]);

        $this->actingAs($employeeOffice);

        // Akses halaman presensi hari ini
        $response = $this->get('/attendance/today');
        $response->assertOk();

        // Shift kantor otomatis dibuat
        $this->assertDatabaseHas('shifts', [
            'user_id' => $employeeOffice->id,
            'branch_id' => $branch->id,
            'status' => 'approved',
        ]);

        $shift = Shift::where('user_id', $employeeOffice->id)->first();
        $this->assertSame('2026-10-14 09:00:00', $shift->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-14 18:00:00', $shift->end_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    /**
     * 7. Non-Happy Path: Karyawan office_hours di hari libur kantor (Minggu) TIDAK dibuatkan shift otomatis.
     */
    public function test_office_hours_does_not_provision_on_weekend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 10:00:00', 'Asia/Jakarta')); // Minggu

        $branch = $this->createBranch();
        $employeeOffice = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'schedule_type' => 'office_hours',
        ]);

        $this->actingAs($employeeOffice);

        $response = $this->get('/attendance/today');
        $response->assertOk();

        // Shift TIDAK boleh terbuat di hari Minggu
        $this->assertDatabaseMissing('shifts', [
            'user_id' => $employeeOffice->id,
        ]);

        Carbon::setTestNow();
    }

    /**
     * 8. Non-Happy Path: Karyawan skema shift operasional biasa yang belum memiliki jadwal TIDAK dibuatkan shift kantor.
     */
    public function test_regular_shift_employee_does_not_auto_provision_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 08:30:00', 'Asia/Jakarta')); // Rabu

        $branch = $this->createBranch();
        $employeeShift = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'schedule_type' => 'shift', // Shift reguler
        ]);

        $this->actingAs($employeeShift);

        $response = $this->get('/');
        $response->assertOk();

        // Shift TIDAK boleh dibuat otomatis untuk karyawan shift
        $this->assertDatabaseMissing('shifts', [
            'user_id' => $employeeShift->id,
        ]);

        Carbon::setTestNow();
    }

    /**
     * 9. Non-Happy Path: Karyawan shift reguler tanpa jadwal mencoba check-in mobile ditolak 422.
     */
    public function test_regular_shift_employee_checkin_fails_without_scheduled_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 08:30:00', 'Asia/Jakarta')); // Rabu

        $branch = $this->createBranch();
        $employeeShift = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'schedule_type' => 'shift',
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employeeShift, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Jadwal Shift wajib diisi', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * 10. Non-Happy Path: Admin menginput schedule_type tidak valid saat membuat karyawan baru ditolak 422.
     */
    public function test_admin_person_store_rejects_invalid_schedule_type(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $branch = $this->createBranch();
        $pos = $this->createPosition();

        $this->actingAs($admin);

        $response = $this->post('/people', [
            'name' => 'Karyawan Invalid Schedule',
            'email' => 'invalid_schedule@example.com',
            'password' => 'Password123!',
            'role' => 'employee',
            'schedule_type' => 'schedule_sembarangan',
            'branch_id' => $branch->id,
            'position_id' => $pos->id,
            'base_salary' => 4000000,
            'hired_at' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('schedule_type');
        $this->assertDatabaseMissing('users', ['email' => 'invalid_schedule@example.com']);
    }

    /**
     * 11. Non-Happy Path: Admin menginput schedule_type tidak valid saat update karyawan ditolak 422.
     */
    public function test_admin_person_update_rejects_invalid_schedule_type(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $branch = $this->createBranch();
        $pos = $this->createPosition();
        $employee = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id, 'schedule_type' => 'shift']);

        $this->actingAs($admin);

        $response = $this->post("/people/{$employee->id}", [
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => 'employee',
            'active' => 1,
            'schedule_type' => 'tipe_salah',
            'branch_id' => $branch->id,
            'position_id' => $pos->id,
            'base_salary' => 4000000,
            'hired_at' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('schedule_type');
        $this->assertSame('shift', $employee->fresh()->schedule_type);
    }

    /**
     * 12. Non-Happy Path: Karyawan office_hours mencoba check-in dengan GPS palsu (is_mocked = true) ditolak.
     */
    public function test_office_hours_checkin_rejected_when_mock_location_detected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 08:55:00', 'Asia/Jakarta')); // Rabu

        $branch = $this->createBranch([
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'radius_m' => 100,
        ]);
        $employeeOffice = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'schedule_type' => 'office_hours',
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employeeOffice, 'sanctum');

        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'accuracy' => 10,
            'is_mocked' => true, // Mock location terdeteksi
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Lokasi GPS terdeteksi palsu', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * 13. Non-Happy Path: Karyawan office_hours check-in di luar radius cabang ditolak geofence.
     */
    public function test_office_hours_checkin_rejected_outside_geofence_radius(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 08:55:00', 'Asia/Jakarta')); // Rabu

        $branch = $this->createBranch([
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'radius_m' => 100, // 100 meter
        ]);
        $employeeOffice = $this->createUser([
            'role' => 'employee',
            'branch_id' => $branch->id,
            'schedule_type' => 'office_hours',
        ]);

        $slot = intdiv(now()->timestamp, AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = AttendanceService::qr($branch, $slot);

        $this->actingAs($employeeOffice, 'sanctum');

        // Koordinat berjarak ratusan meter dari cabang
        $response = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code' => $validQr,
            'latitude' => -6.185392,
            'longitude' => 106.837153,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Batas radius cabang', $response->json('message'));

        Carbon::setTestNow();
    }

    /**
     * 14. Happy Path: Persetujuan cuti unpaid_leave memastikan semua hari berstatus paid = false.
     */
    public function test_unpaid_leave_approval_marks_all_days_as_unpaid(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id, 'annual_leave_quota' => 0]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        $startDate = Carbon::now('Asia/Jakarta')->addDays(3)->toDateString();
        $endDate = Carbon::now('Asia/Jakarta')->addDays(4)->toDateString();

        $this->actingAs($employee, 'sanctum');
        $resStore = $this->postJson('/api/v1/leave', [
            'type' => 'unpaid_leave',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Keperluan keluarga tanpa gaji',
        ]);
        $leaveId = $resStore->json('data.id');

        // Review oleh manajer
        $this->actingAs($manager, 'sanctum');
        $resReview = $this->postJson("/api/v1/leave/{$leaveId}/review", [
            'approved_dates' => [$startDate, $endDate],
            'review_note' => 'Disetujui sebagai cuti tanpa gaji.',
        ]);

        $resReview->assertOk();

        // Pastikan seluruh hari cuti bernilai paid = false
        $leave = LeaveRequest::with('days')->findOrFail($leaveId);
        $this->assertSame('approved', $leave->status);
        $this->assertCount(2, $leave->days);
        foreach ($leave->days as $day) {
            $this->assertFalse((bool) $day->paid);
        }
    }
}

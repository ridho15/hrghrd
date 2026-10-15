<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LeaveDay;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\Shift;
use App\Models\ShiftExchange;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveAndScheduleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function createShift(User $user, Branch $branch, array $attributes = []): Shift
    {
        $start = Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0, 0);
        $end = $start->copy()->setTime(16, 0, 0);

        return Shift::create(array_merge([
            'branch_id'   => $branch->id,
            'user_id'     => $user->id,
            'position_id' => $user->position_id,
            'start_at'    => $start->toDateTimeString(),
            'end_at'      => $end->toDateTimeString(),
            'status'      => 'approved',
            'version'     => 1,
        ], $attributes));
    }

    /**
     * 1. Pengajuan Hari Libur (day_off) tidak memotong kuota cuti tahunan.
     */
    public function test_employee_can_submit_day_off_without_deducting_annual_leave_quota(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition();
        $employee = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id, 'annual_leave_quota' => 12]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        $startDate = Carbon::now('Asia/Jakarta')->addDays(5)->toDateString();
        $endDate = Carbon::now('Asia/Jakarta')->addDays(6)->toDateString();

        // Karyawan dilarang mengajukan day_off secara mandiri (403)
        $this->actingAs($employee);
        $resEmp = $this->postJson('/api/v1/leave', [
            'type'       => 'day_off',
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'reason'     => 'Pengajuan hari libur mingguan untuk urusan keluarga penting',
        ]);
        $resEmp->assertStatus(403);

        // Manajer dapat mendaftarkan day_off terpusat atas nama karyawan
        $this->actingAs($manager);
        $resStore = $this->postJson('/api/v1/leave', [
            'user_id'    => $employee->id,
            'type'       => 'day_off',
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'reason'     => 'Pengajuan hari libur mingguan untuk urusan keluarga penting',
        ]);

        $resStore->assertStatus(201);
        $leaveId = $resStore->json('data.id');

        $this->assertDatabaseHas('leave_requests', [
            'id'     => $leaveId,
            'type'   => 'day_off',
            'status' => 'pending',
        ]);

        // Super Admin menyetujui pengajuan hari libur
        $admin = $this->createUser(['role' => 'admin']);
        $this->actingAs($admin);
        $resReview = $this->postJson("/api/v1/leave/{$leaveId}/review", [
            'approved_dates' => [$startDate, $endDate],
            'paid_dates'     => [$startDate, $endDate],
            'review_note'    => 'Disetujui libur mingguan',
        ]);

        $resReview->assertOk();

        // Kuota cuti tahunan karyawan HARUS TETAP 12 hari (tidak berkurang)
        $this->assertSame(12, $employee->fresh()->remainingLeaveDays());
        $this->assertSame(0, $employee->fresh()->usedLeaveDays((int) now('Asia/Jakarta')->year));
    }

    /**
     * 2. Pengajuan Izin Sakit dengan lampiran surat dokter berukuran s.d. 5 MB diterima.
     */
    public function test_employee_can_submit_sick_leave_with_5mb_certificate(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $file = UploadedFile::fake()->create('surat_dokter.pdf', 4500, 'application/pdf'); // 4.5 MB
        $startDate = Carbon::now('Asia/Jakarta')->toDateString();

        $this->actingAs($employee);
        $response = $this->postJson('/api/v1/leave', [
            'type'        => 'sick',
            'start_date'  => $startDate,
            'end_date'    => $startDate,
            'reason'      => 'Demam tinggi dan flu berat memerlukan istirahat dokter',
            'certificate' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertTrue($response->json('data.has_certificate'));
    }

    /**
     * 3. Pengajuan Izin Sakit yang melampaui toleransi mundur (> 3 hari lalu) ditolak 422.
     */
    public function test_employee_cannot_submit_sick_leave_more_than_3_days_in_past(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $pastDate = Carbon::now('Asia/Jakarta')->subDays(10)->toDateString();

        $this->actingAs($employee);
        $response = $this->postJson('/api/v1/leave', [
            'type'       => 'sick',
            'start_date' => $pastDate,
            'end_date'   => $pastDate,
            'reason'     => 'Klaim sakit masa lampau yang sudah terlalu lama terjadi',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('3 hari ke belakang', $response->json('message'));
    }

    /**
     * 4. Pergantian Cuti (leave_exchange): Menukar tanggal cuti aktif yang disetujui sebelumnya.
     */
    public function test_employee_can_exchange_active_leave_and_cancels_original_leave(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id, 'annual_leave_quota' => 12]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        $oldDate = Carbon::now('Asia/Jakarta')->addDays(10)->toDateString();

        // 1. Cuti awal disetujui
        $origLeave = LeaveRequest::create([
            'user_id'    => $employee->id,
            'created_by' => $employee->id,
            'type'       => 'leave',
            'start_date' => $oldDate,
            'end_date'   => $oldDate,
            'reason'     => 'Cuti tahunan yang semula direncanakan',
            'status'     => 'approved',
        ]);
        LeaveDay::create([
            'leave_request_id' => $origLeave->id,
            'date'             => $oldDate,
            'status'           => 'approved',
            'paid'             => true,
        ]);

        $this->assertSame(11, $employee->fresh()->remainingLeaveDays());

        // 2. Karyawan dilarang mengajukan pergantian cuti mandiri (403)
        $newDate = Carbon::now('Asia/Jakarta')->addDays(15)->toDateString();

        $this->actingAs($employee);
        $resEmp = $this->postJson('/api/v1/leave', [
            'type'              => 'leave_exchange',
            'original_leave_id' => $origLeave->id,
            'start_date'        => $newDate,
            'end_date'          => $newDate,
            'reason'            => 'Menggeser jadwal cuti karena ada penyesuaian acara keluarga',
        ]);
        $resEmp->assertStatus(403);

        // Manajer memproses pergantian cuti terpusat untuk karyawan
        $this->actingAs($manager);
        $resExchange = $this->postJson('/api/v1/leave', [
            'user_id'           => $employee->id,
            'type'              => 'leave_exchange',
            'original_leave_id' => $origLeave->id,
            'start_date'        => $newDate,
            'end_date'          => $newDate,
            'reason'            => 'Menggeser jadwal cuti karena ada penyesuaian acara keluarga',
        ]);

        $resExchange->assertStatus(201);
        $newLeaveId = $resExchange->json('data.id');

        // 3. Super Admin menyetujui pergantian cuti
        $admin = $this->createUser(['role' => 'admin']);
        $this->actingAs($admin);
        $resReview = $this->postJson("/api/v1/leave/{$newLeaveId}/review", [
            'approved_dates' => [$newDate],
            'paid_dates'     => [$newDate],
            'review_note'    => 'Persetujuan penggeseran tanggal cuti',
        ]);
        $resReview->assertOk();

        // Verifikasi hasil:
        // Cuti lama berstatus cancelled
        $this->assertSame('cancelled', $origLeave->fresh()->status);
        // Sisa kuota tetap berkurang 1 (bukan 2), karena tanggal lama dibatalkan dan digantikan tanggal baru
        $this->assertSame(11, $employee->fresh()->remainingLeaveDays());
    }

    /**
     * 5. Pergantian Cuti milik karyawan lain ditolak 403.
     */
    public function test_employee_cannot_exchange_another_employees_leave(): void
    {
        $branch = $this->createBranch();
        $employeeA = $this->createUser(['branch_id' => $branch->id]);
        $employeeB = $this->createUser(['branch_id' => $branch->id]);

        $origLeave = LeaveRequest::create([
            'user_id'    => $employeeA->id,
            'created_by' => $employeeA->id,
            'type'       => 'leave',
            'start_date' => Carbon::now('Asia/Jakarta')->addDays(10)->toDateString(),
            'end_date'   => Carbon::now('Asia/Jakarta')->addDays(10)->toDateString(),
            'reason'     => 'Cuti milik Karyawan A',
            'status'     => 'approved',
        ]);

        $this->actingAs($employeeB);
        $response = $this->postJson('/api/v1/leave', [
            'type'              => 'leave_exchange',
            'original_leave_id' => $origLeave->id,
            'start_date'        => Carbon::now('Asia/Jakarta')->addDays(12)->toDateString(),
            'end_date'          => Carbon::now('Asia/Jakarta')->addDays(12)->toDateString(),
            'reason'            => 'Karyawan B mencoba membajak cuti milik Karyawan A',
        ]);

        // Gagal divalidasi/ditolak akses
        $this->assertTrue(in_array($response->status(), [403, 422], true));
    }

    /**
     * 6. HRD menyetujui Izin Sakit dengan opsi vacate_shifts:
     * Shift karyawan otomatis dikosongkan (user_id = null) menjadi Slot Kosong Terbuka setingkat.
     */
    public function test_manager_approves_sick_leave_with_vacate_shifts_opens_slot_for_same_position(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Barista']);

        $employeeSick = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id, 'role' => 'employee']);
        $manager = $this->createUser(['branch_id' => $branch->id, 'role' => 'manager']);

        // Karyawan memiliki shift di hari H
        $shiftDate = Carbon::now('Asia/Jakarta')->addDays(2)->toDateString();
        $shift = $this->createShift($employeeSick, $branch, [
            'start_at' => Carbon::parse($shiftDate, 'Asia/Jakarta')->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::parse($shiftDate, 'Asia/Jakarta')->setTime(16, 0)->toDateTimeString(),
        ]);

        // Karyawan mengajukan sakit
        $this->actingAs($employeeSick);
        $resStore = $this->postJson('/api/v1/leave', [
            'type'        => 'sick',
            'start_date'  => $shiftDate,
            'end_date'    => $shiftDate,
            'reason'      => 'Sakit tifus rawat inap memerlukan istirahat dokter',
            'certificate' => UploadedFile::fake()->create('surat.pdf', 500, 'application/pdf'),
        ]);
        $leaveId = $resStore->json('data.id');

        // Manajer mereview dengan opsi vacate_shifts = true
        $this->actingAs($manager);
        $resReview = $this->postJson("/api/v1/leave/{$leaveId}/review", [
            'approved_dates' => [$shiftDate],
            'paid_dates'     => [$shiftDate],
            'review_note'    => 'Disetujui sakit rawat inap, buka jadwal shift agar staf lain bisa menggantikan',
            'vacate_shifts'  => true,
        ]);
        $resReview->assertOk();

        // Verifikasi hasil:
        // Shift dikosongkan (user_id null) dan mempertahankan position_id Barista
        $freshShift = $shift->fresh();
        $this->assertNull($freshShift->user_id);
        $this->assertSame($pos->id, $freshShift->position_id);
        $this->assertTrue($freshShift->is_open_slot);
    }

    /**
     * 7. Slot Kosong yang baru ditinggalkan karyawan sakit dapat diambil oleh rekan setingkat (Barista)
     * melalui fitur Ganti Shift (change_shift).
     */
    public function test_vacated_slot_can_be_claimed_by_peer_at_same_position_level_via_change_shift(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition(['name' => 'Barista']);

        $employeeA = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id]);
        $employeeB = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id]);
        $manager = $this->createUser(['branch_id' => $branch->id, 'role' => 'manager']);

        // Shift kosong hasil pelepasan sakit Karyawan A
        $openSlot = Shift::create([
            'branch_id'   => $branch->id,
            'user_id'     => null,
            'position_id' => $pos->id,
            'start_at'    => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0)->toDateTimeString(),
            'end_at'      => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0)->toDateTimeString(),
            'status'      => 'approved',
            'version'     => 1,
        ]);

        // Karyawan B memiliki shift di hari lain yang ingin ditukar
        $shiftB = $this->createShift($employeeB, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(5)->setTime(13, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(5)->setTime(21, 0)->toDateTimeString(),
        ]);

        // Karyawan B melihat daftar open slots via mobile API
        $this->actingAs($employeeB);
        $resList = $this->getJson('/api/v1/shifts/open-slots');
        $resList->assertOk();
        $this->assertTrue(collect($resList->json('data'))->pluck('id')->contains($openSlot->id));

        // Karyawan B dilarang mengajukan klaim open slot mandiri (403)
        $this->actingAs($employeeB);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'               => 'change_shift',
            'target_shift_id'    => $openSlot->id,
            'requester_shift_id' => $shiftB->id,
            'reason'             => 'Mengambil slot kosong Barista dan menukar shift kerja saya',
        ]);
        $resEmp->assertStatus(403);

        // Manajer memproses Ganti Shift ke open slot tersebut
        $this->actingAs($manager);
        $resClaim = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'               => 'change_shift',
            'target_shift_id'    => $openSlot->id,
            'requester_shift_id' => $shiftB->id,
            'reason'             => 'Mengambil slot kosong Barista dan menukar shift kerja saya',
        ]);
        $resClaim->assertStatus(201);
        $exchangeId = $resClaim->json('data.id');

        // Manajer menyetujui
        $resApprove = $this->postJson("/api/v1/shifts/exchanges/{$exchangeId}/review", [
            'decision' => 'approve',
            'note'     => 'Disetujui manajer',
        ]);
        $resApprove->assertOk();

        // Open slot sekarang menjadi milik Karyawan B
        $this->assertSame($employeeB->id, $openSlot->fresh()->user_id);
        // Shift lama Karyawan B dikosongkan (menjadi slot kosong baru)
        $this->assertNull($shiftB->fresh()->user_id);
    }

    /**
     * 8. Penolakan bila level berbeda mencoba klaim slot kosong (Negative Path: 422 untuk manajer, 403 untuk karyawan).
     */
    public function test_vacated_slot_cannot_be_claimed_by_different_position_level(): void
    {
        $branch = $this->createBranch();
        $posKitchen = $this->createPosition(['name' => 'Kitchen Staff']);
        $posManager = $this->createPosition(['name' => 'Store Supervisor']);

        $employeeKitchen = $this->createUser(['branch_id' => $branch->id, 'position_id' => $posKitchen->id]);

        $openSlotSupervisor = Shift::create([
            'branch_id'   => $branch->id,
            'user_id'     => null,
            'position_id' => $posManager->id,
            'start_at'    => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(8, 0)->toDateTimeString(),
            'end_at'      => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(16, 0)->toDateTimeString(),
            'status'      => 'approved',
            'version'     => 1,
        ]);

        // Karyawan dilarang mengajukan klaim open slot mandiri (403)
        $this->actingAs($employeeKitchen);
        $resEmp = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlotSupervisor->id,
            'reason'          => 'Mencoba ambil slot supervisor padahal level kitchen',
        ]);
        $resEmp->assertStatus(403);

        // Manajer memproses penukaran jadwal tetapi ditolak 422 karena posisi tidak setingkat
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $response = $this->postJson('/api/v1/shifts/exchanges/open-slot', [
            'type'            => 'change_off_day',
            'target_shift_id' => $openSlotSupervisor->id,
            'reason'          => 'Mencoba ambil slot supervisor padahal level kitchen',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('level jabatan yang berbeda', $response->json('message'));
    }

    /**
     * 9. Mobile API: Endpoint GET /api/v1/leave/types mengembalikan informasi tipe dan kuota lengkap.
     */
    public function test_mobile_api_types_endpoint_returns_quotas_and_reschedulable_leaves(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id, 'annual_leave_quota' => 12]);

        $this->actingAs($employee);
        $response = $this->getJson('/api/v1/leave/types');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'annual_leave_quota',
                'used_leave_days',
                'remaining_leave_days',
                'leave_notice_days',
                'types',
                'reschedulable_leaves',
            ],
        ]);

        $types = collect($response->json('data.types'))->pluck('key')->all();
        $this->assertContains('leave', $types);
        $this->assertContains('sick', $types);
        $this->assertNotContains('day_off', $types);
        $this->assertNotContains('leave_exchange', $types);

        // Bagi manajer, types lengkap tersedia
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);
        $resMgr = $this->getJson('/api/v1/leave/types');
        $mgrTypes = collect($resMgr->json('data.types'))->pluck('key')->all();
        $this->assertContains('day_off', $mgrTypes);
        $this->assertContains('leave_exchange', $mgrTypes);
    }

    /**
     * 10. Web Interface: Form create dan show dapat diakses dan menampilkan komponen baru.
     */
    public function test_web_interface_displays_new_options(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        $this->actingAs($employee);

        // Halaman create form untuk karyawan tidak menampilkan day_off / leave_exchange
        $resCreate = $this->get('/leave/create');
        $resCreate->assertOk();
        $resCreate->assertDontSee('Pengajuan Hari Libur (Day Off)');
        $resCreate->assertDontSee('Pergantian Tanggal Cuti (Reschedule)');

        // Karyawan dilarang submit day_off
        $startDate = Carbon::now('Asia/Jakarta')->addDays(7)->toDateString();
        $resPostEmp = $this->post('/leave', [
            'type'       => 'day_off',
            'start_date' => $startDate,
            'end_date'   => $startDate,
            'reason'     => 'Pengajuan hari libur mingguan via web interface',
        ]);
        $resPostEmp->assertSessionHasErrors('type');

        // Manajer dapat melihat opsi day_off dan membuat pengajuan
        $this->actingAs($manager);
        $resCreateMgr = $this->get('/leave/create');
        $resCreateMgr->assertOk();
        $resCreateMgr->assertSee('Pengajuan Hari Libur (Day Off)');
        $resCreateMgr->assertSee('Pergantian Tanggal Cuti (Reschedule)');

        $resPost = $this->post('/leave', [
            'user_id'    => $employee->id,
            'type'       => 'day_off',
            'start_date' => $startDate,
            'end_date'   => $startDate,
            'reason'     => 'Pengajuan hari libur mingguan via web interface',
        ]);
        $resPost->assertRedirect();

        $leave = LeaveRequest::where('type', 'day_off')->first();
        $this->assertNotNull($leave);

        // Halaman show dossier
        $resShow = $this->get("/leave/{$leave->id}");
        $resShow->assertOk();
        $resShow->assertSee('Hari Libur (Day Off)');
    }

    /**
     * 11. REST API: AttendanceResource & ShiftResource tidak memformat "0 Menit" saat tepat waktu.
     */
    public function test_attendance_resource_does_not_format_zero_minutes_when_on_time(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch);

        // 1. Presensi Hadir Tepat Waktu (0 menit keterlambatan, 0 menit lembur)
        $attOnTime = \App\Models\Attendance::create([
            'shift_id'         => $shift->id,
            'user_id'          => $employee->id,
            'checkin_at'       => Carbon::parse($shift->start_at),
            'checkout_at'      => Carbon::parse($shift->end_at),
            'status'           => 'present',
            'late_minutes'     => 0,
            'late_units'       => 0,
            'overtime_minutes' => 0,
        ]);

        $resourceOnTime = (new \App\Http\Resources\AttendanceResource($attOnTime))->toArray(request());

        $this->assertSame(0, $resourceOnTime['late_minutes']);
        $this->assertFalse($resourceOnTime['is_late']);
        $this->assertNull($resourceOnTime['late_display'], 'Karyawan tepat waktu tidak boleh memiliki string late_display');
        $this->assertSame('Tepat Waktu', $resourceOnTime['late_badge_label']);
        $this->assertFalse($resourceOnTime['has_overtime']);
        $this->assertNull($resourceOnTime['overtime_display'], 'Tidak boleh memunculkan 0 Menit untuk lembur');

        // Uji ShiftResource
        $shift->setRelation('attendance', $attOnTime);
        $shiftResourceOnTime = (new \App\Http\Resources\ShiftResource($shift))->toArray(request());
        $this->assertFalse($shiftResourceOnTime['attendance']['is_late']);
        $this->assertNull($shiftResourceOnTime['attendance']['late_display']);
        $this->assertSame('Tepat Waktu', $shiftResourceOnTime['attendance']['late_badge_label']);
        $this->assertNull($shiftResourceOnTime['attendance']['overtime_display']);

        // 2. Presensi Terlambat & Lembur
        $shiftLate = $this->createShift($employee, $branch, [
            'start_at' => Carbon::now('Asia/Jakarta')->addDays(5)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::now('Asia/Jakarta')->addDays(5)->setTime(16, 0)->toDateTimeString(),
        ]);
        $attLate = \App\Models\Attendance::create([
            'shift_id'         => $shiftLate->id,
            'user_id'          => $employee->id,
            'checkin_at'       => Carbon::parse($shiftLate->start_at)->addMinutes(25),
            'checkout_at'      => Carbon::parse($shiftLate->end_at)->addMinutes(45),
            'status'           => 'late',
            'late_minutes'     => 25,
            'late_units'       => 1,
            'overtime_minutes' => 45,
        ]);

        $resourceLate = (new \App\Http\Resources\AttendanceResource($attLate))->toArray(request());
        $this->assertTrue($resourceLate['is_late']);
        $this->assertSame('25 Menit', $resourceLate['late_display']);
        $this->assertSame('Terlambat 25m', $resourceLate['late_badge_label']);
        $this->assertTrue($resourceLate['has_overtime']);
        $this->assertSame('45 Menit', $resourceLate['overtime_display']);
    }

    /**
     * 12. Guard Proteksi vacate_shifts: Hanya shift masa depan tanpa absensi yang dikosongkan.
     */
    public function test_vacate_shifts_ignores_shifts_with_existing_attendance_or_past_time(): void
    {
        $branch = $this->createBranch();
        $pos = $this->createPosition();
        $employee = $this->createUser(['branch_id' => $branch->id, 'position_id' => $pos->id]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        $targetDate = Carbon::now('Asia/Jakarta')->addDays(4)->toDateString();

        // 1. Shift masa depan tanpa absensi (Harus dikosongkan)
        $futureShiftClean = $this->createShift($employee, $branch, [
            'start_at' => Carbon::parse($targetDate)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::parse($targetDate)->setTime(16, 0)->toDateTimeString(),
        ]);

        // 2. Shift masa depan tapi sudah ada record presensi/pengecualian (TIDAK boleh dikosongkan)
        $futureShiftAttended = $this->createShift($employee, $branch, [
            'start_at' => Carbon::parse($targetDate)->setTime(16, 30)->toDateTimeString(),
            'end_at'   => Carbon::parse($targetDate)->setTime(23, 0)->toDateTimeString(),
        ]);
        \App\Models\Attendance::create([
            'shift_id'     => $futureShiftAttended->id,
            'user_id'      => $employee->id,
            'checkin_at'   => Carbon::parse($targetDate)->setTime(16, 25)->toDateTimeString(),
            'status'       => 'present',
            'late_minutes' => 0,
            'late_units'   => 0,
        ]);

        // 3. Shift masa lalu (TIDAK boleh dikosongkan)
        $pastDate = Carbon::now('Asia/Jakarta')->subDays(1)->toDateString();
        $pastShift = $this->createShift($employee, $branch, [
            'start_at' => Carbon::parse($pastDate)->setTime(8, 0)->toDateTimeString(),
            'end_at'   => Carbon::parse($pastDate)->setTime(16, 0)->toDateTimeString(),
        ]);

        // Ajukan izin sakit untuk tanggal target dan tanggal lampau
        $this->actingAs($employee);
        $leave = (new \App\Services\LeaveService())->submit($employee, [
            'type'       => 'sick',
            'start_date' => $pastDate,
            'end_date'   => $targetDate,
            'reason'     => 'Izin sakit rawat inap demam berdarah',
        ], UploadedFile::fake()->create('surat.pdf', 300, 'application/pdf'));

        // Manager review dengan vacate_shifts = true
        $this->actingAs($manager);
        (new \App\Services\LeaveService())->review(
            $leave,
            $manager,
            [$pastDate, $targetDate],
            [$pastDate, $targetDate],
            'Disetujui manajer dengan pelepasan shift',
            [],
            true // vacateShifts = true
        );

        // Verifikasi:
        // 1. futureShiftClean dikosongkan (user_id null)
        $this->assertNull($futureShiftClean->fresh()->user_id);
        $this->assertSame($pos->id, $futureShiftClean->fresh()->position_id);

        // 2. futureShiftAttended tetap milik employee (tidak di-null-kan)
        $this->assertSame($employee->id, $futureShiftAttended->fresh()->user_id);

        // 3. pastShift tetap milik employee (tidak di-null-kan)
        $this->assertSame($employee->id, $pastShift->fresh()->user_id);
    }

    /**
     * 13. Web View: shifts.show menampilkan tanda strip '—' jika lembur bernilai 0.
     */
    public function test_shifts_show_blade_displays_dash_for_zero_overtime(): void
    {
        $branch = $this->createBranch();
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch);
        \App\Models\Attendance::create([
            'shift_id'         => $shift->id,
            'user_id'          => $employee->id,
            'checkin_at'       => Carbon::parse($shift->start_at),
            'checkout_at'      => Carbon::parse($shift->end_at),
            'status'           => 'present',
            'late_minutes'     => 0,
            'late_units'       => 0,
            'overtime_minutes' => 0,
        ]);

        $this->actingAs($manager);
        $response = $this->get("/shifts/{$shift->id}");

        $response->assertOk();
        $response->assertSee('Tepat Waktu');
        $response->assertDontSee('0 Menit');
    }

    /**
     * 14. Web View: Karyawan biasa tidak melihat kuota cuti di /leave, /leave/create, dan profil /people/{id}.
     */
    public function test_employee_does_not_see_leave_quota_cards_on_leave_views_and_profile(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'role'               => 'employee',
            'branch_id'          => $branch->id,
            'annual_leave_quota' => 12,
        ]);

        $this->actingAs($employee);

        // 1. /leave: Tidak menampilkan kartu Kuota Cuti & Sisa Kuota Cuti
        $resLeave = $this->get('/leave');
        $resLeave->assertOk();
        $resLeave->assertDontSee('Kuota Cuti (');
        $resLeave->assertDontSee('Sisa Kuota Cuti');

        // 2. /leave/create: Tidak menampilkan widget Sisa Cuti Tahunan & teks Memotong Kuota
        $resCreate = $this->get('/leave/create');
        $resCreate->assertOk();
        $resCreate->assertDontSee('Sisa Cuti Tahunan');
        $resCreate->assertDontSee('Memotong Kuota');

        // 3. /people/{id}: Saat karyawan membuka profilnya sendiri, kartu Sisa Cuti tidak tampil
        $resProfile = $this->get("/people/{$employee->id}");
        $resProfile->assertOk();
        $resProfile->assertDontSee('Sisa Cuti (');
    }

    /**
     * 15. Web View: Manager dan Admin tetap dapat melihat kartu kuota cuti untuk kebutuhan manajerial.
     */
    public function test_manager_can_still_see_leave_quota_cards_on_leave_views_and_profile(): void
    {
        $branch = $this->createBranch();
        $manager = $this->createUser([
            'role'               => 'manager',
            'branch_id'          => $branch->id,
            'annual_leave_quota' => 12,
        ]);
        $employee = $this->createUser([
            'role'               => 'employee',
            'branch_id'          => $branch->id,
            'annual_leave_quota' => 12,
        ]);

        $this->actingAs($manager);

        // 1. /leave: Menampilkan kartu Kuota Cuti & Sisa Kuota Cuti untuk manajer
        $resLeave = $this->get('/leave');
        $resLeave->assertOk();
        $resLeave->assertSee('Kuota Cuti (');
        $resLeave->assertSee('Sisa Kuota Cuti');

        // 2. /leave/create: Menampilkan widget Sisa Cuti Tahunan
        $resCreate = $this->get('/leave/create');
        $resCreate->assertOk();
        $resCreate->assertSee('Sisa Cuti Tahunan');

        // 3. /people/{id}: Menampilkan kartu Sisa Cuti saat meninjau staf
        $resProfile = $this->get("/people/{$employee->id}");
        $resProfile->assertOk();
        $resProfile->assertSee('Sisa Cuti (');
    }

    /**
     * 16. Mobile API: GET /api/v1/leave/types menyembunyikan kuota (null) untuk karyawan biasa, namun ada untuk manajer.
     */
    public function test_api_leave_types_masks_leave_quota_for_regular_employees(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'role'               => 'employee',
            'branch_id'          => $branch->id,
            'annual_leave_quota' => 12,
        ]);
        $manager = $this->createUser([
            'role'               => 'manager',
            'branch_id'          => $branch->id,
            'annual_leave_quota' => 12,
        ]);

        // Karyawan biasa -> kuota bernilai null
        $this->actingAs($employee, 'sanctum');
        $resEmp = $this->getJson('/api/v1/leave/types');
        $resEmp->assertOk();
        $resEmp->assertJsonPath('data.annual_leave_quota', null);
        $resEmp->assertJsonPath('data.used_leave_days', null);
        $resEmp->assertJsonPath('data.remaining_leave_days', null);

        // Manajer -> kuota integer tersedia
        $this->actingAs($manager, 'sanctum');
        $resMgr = $this->getJson('/api/v1/leave/types');
        $resMgr->assertOk();
        $resMgr->assertJsonPath('data.annual_leave_quota', 12);
        $this->assertNotNull($resMgr->json('data.remaining_leave_days'));
    }

    /**
     * 17. Pengajuan Cuti Tanpa Gaji (unpaid_leave) berhasil diajukan & disetujui meski kuota cuti 0.
     */
    public function test_employee_can_submit_and_approve_unpaid_leave_even_with_zero_quota(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'role'               => 'employee',
            'branch_id'          => $branch->id,
            'annual_leave_quota' => 0, // Kuota habis / 0
        ]);
        $manager = $this->createUser([
            'role'      => 'manager',
            'branch_id' => $branch->id,
        ]);

        $startDate = Carbon::now('Asia/Jakarta')->addDays(2)->toDateString();
        $endDate = Carbon::now('Asia/Jakarta')->addDays(3)->toDateString();

        // Karyawan mengajukan unpaid_leave secara mandiri
        $this->actingAs($employee, 'sanctum');
        $res = $this->postJson('/api/v1/leave', [
            'type'       => 'unpaid_leave',
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'reason'     => 'Keperluan keluarga mendesak tanpa gaji',
        ]);

        $res->assertStatus(201);
        $leaveId = $res->json('data.id');

        $this->assertDatabaseHas('leave_requests', [
            'id'      => $leaveId,
            'user_id' => $employee->id,
            'type'    => 'unpaid_leave',
            'status'  => 'pending',
        ]);

        // Manajer meninjau dan menyetujui
        $this->actingAs($manager, 'sanctum');
        $resReview = $this->postJson("/api/v1/leave/{$leaveId}/review", [
            'approved_dates' => [$startDate, $endDate],
            'review_note'    => 'Disetujui sebagai cuti tanpa gaji resmi.',
        ]);

        $resReview->assertOk();

        // Pastikan seluruh hari berstatus approved dan paid = false (unpaid)
        $this->assertDatabaseHas('leave_days', [
            'leave_request_id' => $leaveId,
            'date'             => $startDate,
            'status'           => 'approved',
            'paid'             => false,
        ]);
        $this->assertDatabaseHas('leave_days', [
            'leave_request_id' => $leaveId,
            'date'             => $endDate,
            'status'           => 'approved',
            'paid'             => false,
        ]);
    }

    /**
     * 18. Karyawan Khusus (office_hours): Shift kantor otomatis ter-generate di hari kerja (Senin–Jumat) saat buka dashboard.
     */
    public function test_office_hours_employee_auto_provisions_shift_on_weekday_web_dashboard(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 08:50:00', 'Asia/Jakarta')); // Rabu (Weekday)

        $branch = $this->createBranch();
        $employeeOffice = $this->createUser([
            'role'          => 'employee',
            'branch_id'     => $branch->id,
            'schedule_type' => 'office_hours',
        ]);

        $this->actingAs($employeeOffice);

        // Karyawan belum memiliki shift manual apapun di database
        $this->assertDatabaseMissing('shifts', [
            'user_id' => $employeeOffice->id,
        ]);

        // Saat karyawan membuka halaman dashboard utama
        $response = $this->get('/');
        $response->assertOk();

        // Shift kantor otomatis ter-generate dan berstatus approved
        $this->assertDatabaseHas('shifts', [
            'user_id'   => $employeeOffice->id,
            'branch_id' => $branch->id,
            'status'    => 'approved',
        ]);

        $shift = Shift::where('user_id', $employeeOffice->id)->first();
        $this->assertNotNull($shift);
        $this->assertSame('2026-10-14 09:00:00', $shift->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-14 18:00:00', $shift->end_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    /**
     * 19. Karyawan Khusus (office_hours): Tidak ada auto-provisioning shift di hari libur kantor (Sabtu/Minggu).
     */
    public function test_office_hours_employee_does_not_provision_on_weekend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 10:00:00', 'Asia/Jakarta')); // Minggu (Weekend)

        $branch = $this->createBranch();
        $employeeOffice = $this->createUser([
            'role'          => 'employee',
            'branch_id'     => $branch->id,
            'schedule_type' => 'office_hours',
        ]);

        $this->actingAs($employeeOffice);

        $shift = (new \App\Services\ShiftService())->ensureOfficeShiftToday($employeeOffice);
        $this->assertNull($shift);

        $this->assertDatabaseMissing('shifts', [
            'user_id' => $employeeOffice->id,
        ]);

        Carbon::setTestNow();
    }

    /**
     * 20. Mobile API: Karyawan office_hours dapat check-in tanpa mengirim shift_id karena otomatis di-resolve.
     */
    public function test_office_hours_employee_can_checkin_via_api_without_explicit_shift_id(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 08:55:00', 'Asia/Jakarta')); // Rabu

        $branch = $this->createBranch([
            'latitude'  => -6.175392,
            'longitude' => 106.827153,
            'radius_m'  => 100,
        ]);
        $employeeOffice = $this->createUser([
            'role'          => 'employee',
            'branch_id'     => $branch->id,
            'schedule_type' => 'office_hours',
        ]);

        $slot = intdiv(now()->timestamp, \App\Services\AttendanceService::QR_INTERVAL_SECONDS);
        $validQr = \App\Services\AttendanceService::qr($branch, $slot);

        $this->actingAs($employeeOffice, 'sanctum');

        $res = $this->postJson('/api/v1/attendance/checkin', [
            'qr_code'   => $validQr,
            'latitude'  => -6.175392,
            'longitude' => 106.827153,
            'accuracy'  => 15,
        ]);

        $res->assertStatus(201);
        $res->assertJsonPath('success', true);

        // Record attendance berhasil dibuat untuk auto-provisioned office shift
        $this->assertDatabaseHas('attendances', [
            'user_id' => $employeeOffice->id,
            'status'  => 'present',
        ]);

        Carbon::setTestNow();
    }

    /**
     * 21. Manajemen Karyawan (People): Admin dapat mengatur dan memperbarui schedule_type.
     */
    public function test_admin_can_set_and_update_schedule_type_via_people_management(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $branch = $this->createBranch();
        $pos = $this->createPosition();

        $this->actingAs($admin);

        // 1. Tambah karyawan baru dengan schedule_type = office_hours
        $resStore = $this->post('/people', [
            'name'          => 'Staf Kantor Pusat',
            'email'         => 'stafkantor@example.com',
            'password'      => 'Password123!',
            'role'          => 'employee',
            'schedule_type' => 'office_hours',
            'branch_id'     => $branch->id,
            'position_id'   => $pos->id,
            'base_salary'   => 4500000,
            'hired_at'      => '2026-01-01',
        ]);

        $resStore->assertRedirect();
        $user = User::where('email', 'stafkantor@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('office_hours', $user->schedule_type);
        $this->assertTrue($user->isOfficeSchedule());

        // 2. Update menjadi schedule_type = shift
        $resUpdate = $this->post("/people/{$user->id}", [
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => 'employee',
            'active'        => 1,
            'schedule_type' => 'shift',
            'branch_id'     => $branch->id,
            'position_id'   => $pos->id,
            'base_salary'   => 4500000,
            'hired_at'      => '2026-01-01',
        ]);

        $resUpdate->assertRedirect();
        $this->assertSame('shift', $user->fresh()->schedule_type);
        $this->assertTrue($user->fresh()->isShiftSchedule());
    }
}


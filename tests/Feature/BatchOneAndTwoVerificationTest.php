<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EmployeeImport;
use App\Services\PayrollCalculator;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchOneAndTwoVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_with_manual_adjustment_can_be_approved_cleanly(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'hired_at' => '2026-01-01',
            'ended_at' => null,
            'base_salary' => 5000000,
        ]);

        $pastMonth = '2026-01';

        // 1. Add manual adjustment (Bonus)
        PayrollAdjustment::create([
            'user_id' => $employee->id,
            'month' => $pastMonth,
            'amount' => 500000,
            'reason' => 'Bonus insentif pencapaian target',
            'created_by' => auth()->id(),
        ]);

        // 2. Generate payroll draft
        $calculator = new PayrollCalculator();
        $payrollService = new PayrollService();
        $run = $payrollService->generate($branch->id, $pastMonth, $calculator);

        $this->assertSame('draft', $run->status);

        // 3. Approve payroll (before our fix, this crashed with HTTP 409 'Draf payroll berubah' due to stdClass vs array)
        $response = $this->post('/payroll/approve', [
            'branch_id' => $branch->id,
            'month' => $pastMonth,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('ok', 'Payroll disetujui.');
        $this->assertSame('approved', $run->fresh()->status);
    }

    public function test_auto_absent_preserves_checkin_evidence_and_timestamp(): void
    {
        $branch = $this->createBranch(['latitude' => -6.175392, 'longitude' => 106.827153, 'radius_m' => 100]);
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => now('Asia/Jakarta')->subMinutes(75)->toDateTimeString(), // 75 mins late (> 60m threshold)
            'end_at' => now('Asia/Jakarta')->addHours(6)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $service = new AttendanceService();

        try {
            $service->act($employee, $shift->id, 'in', [
                'action' => 'in',
                'qr_code' => AttendanceService::qr($branch),
                'challenge' => 'test-challenge',
                'latitude' => -6.175392,
                'longitude' => 106.827153,
                'accuracy' => 12,
            ], 'test-device-token-absent');
        } catch (\Illuminate\Validation\ValidationException) {
            // Service returns message / throws validation exception
        }

        $absent = Attendance::where('shift_id', $shift->id)->where('user_id', $employee->id)->first();
        $this->assertNotNull($absent);
        $this->assertSame('absent', $absent->status);
        $this->assertNotNull($absent->checkin_at);
        $this->assertIsArray($absent->checkin_evidence);
        $this->assertSame(hash('sha256', 'test-device-token-absent'), $absent->checkin_evidence['device_hash']);
    }

    public function test_import_rejects_row_with_empty_hired_at(): void
    {
        $branch = $this->createBranch(['code' => 'CAB-TEST-1']);
        $importer = new EmployeeImport();

        $rows = [
            ['Nama', 'Email', 'Cabang', 'Jabatan', 'Tanggal Mulai', 'Gaji Pokok', 'Status'],
            ['Budi Santoso', 'budi.empty@example.test', 'CAB-TEST-1', 'Staf', '', '4000000', 'aktif'], // Empty hired_at!
        ];

        $map = [
            'name' => 0,
            'email' => 1,
            'branch' => 2,
            'position' => 3,
            'hired_at' => 4,
            'base_salary' => 5,
            'active' => 6,
        ];

        $result = $importer->run($rows, $map);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('tanggal mulai kerja wajib diisi', $result['errors'][0]);
        $this->assertDatabaseMissing('users', ['email' => 'budi.empty@example.test']);
    }

    public function test_import_matches_position_case_insensitively_without_duplicate(): void
    {
        $branch = $this->createBranch(['code' => 'CAB-TEST-2']);
        $position = Position::create(['name' => 'Kasir']);

        $importer = new EmployeeImport();

        $rows = [
            ['Nama', 'Email', 'Cabang', 'Jabatan', 'Tanggal Mulai', 'Gaji Pokok', 'Status'],
            ['Siti Kasir', 'siti.kasir@example.test', 'CAB-TEST-2', 'kasir ', '01/02/2026', '3500000', 'aktif'], // lowercase with trailing space
        ];

        $map = [
            'name' => 0,
            'email' => 1,
            'branch' => 2,
            'position' => 3,
            'hired_at' => 4,
            'base_salary' => 5,
            'active' => 6,
        ];

        $result = $importer->run($rows, $map);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['failed']);

        $user = User::where('email', 'siti.kasir@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame($position->id, $user->position_id);
        // Ensure no new duplicate position was created
        $this->assertSame(1, Position::whereRaw('LOWER(TRIM(name)) = ?', ['kasir'])->count());
    }

    public function test_employee_can_view_their_own_profile(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $this->actingAs($employee);

        $response = $this->get("/people/{$employee->id}");

        $response->assertStatus(200);
        $response->assertSee($employee->name);
        $response->assertSee('Dossier Karyawan');
    }

    public function test_employee_cannot_view_other_employees_profile(): void
    {
        $branch = $this->createBranch();
        $employeeA = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $employeeB = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $this->actingAs($employeeA);

        $response = $this->get("/people/{$employeeB->id}");

        $response->assertStatus(403);
    }
}

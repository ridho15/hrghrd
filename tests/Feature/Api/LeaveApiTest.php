<?php

namespace Tests\Feature\Api;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_submission_by_employee(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);
        Sanctum::actingAs($employee, ['employee']);

        $response = $this->postJson('/api/v1/leave', [
            'type'       => 'leave',
            'start_date' => '2026-11-01',
            'end_date'   => '2026-11-02',
            'reason'     => 'Keperluan keluarga penting di luar kota.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'leave')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $employee->id,
            'type'    => 'leave',
            'status'  => 'pending',
        ]);

        $this->assertDatabaseCount('leave_days', 2);
    }

    public function test_leave_submission_with_certificate(): void
    {
        Storage::fake('local');
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);
        Sanctum::actingAs($employee, ['employee']);

        $file = UploadedFile::fake()->create('surat_dokter.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/leave', [
            'type'        => 'sick',
            'start_date'  => '2026-11-05',
            'end_date'    => '2026-11-06',
            'reason'      => 'Sakit demam berdarah opname dokter.',
            'certificate' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_certificate', true);

        $leave = LeaveRequest::first();
        $this->assertNotNull($leave->certificate_path);
        Storage::disk('local')->assertExists($leave->certificate_path);
    }

    public function test_leave_certificate_download_authorization(): void
    {
        Storage::fake('local');
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $outsider = $this->createUser(['role' => 'employee']);

        $leave = LeaveRequest::create([
            'user_id'          => $employee->id,
            'created_by'       => $employee->id,
            'type'             => 'sick',
            'start_date'       => '2026-11-05',
            'end_date'         => '2026-11-05',
            'reason'           => 'Sakit demam, lampiran surat dokter terlampir.',
            'status'           => 'pending',
            'certificate_path' => 'certificates/surat_dokter.pdf',
            'certificate_name' => 'surat_dokter.pdf',
        ]);
        Storage::disk('local')->put($leave->certificate_path, 'fake-pdf-content');

        // Pemilik pengajuan sendiri bisa mengunduh.
        Sanctum::actingAs($employee, ['employee']);
        $this->get("/api/v1/leave/{$leave->id}/certificate")->assertStatus(200);

        // Manager cabangnya bisa mengunduh (perlu meninjau sebelum memutuskan).
        Sanctum::actingAs($manager, ['manager', 'employee']);
        $this->get("/api/v1/leave/{$leave->id}/certificate")->assertStatus(200);

        // Karyawan dari cabang/tempat lain yang tidak terkait TIDAK bisa mengunduh.
        Sanctum::actingAs($outsider, ['employee']);
        $this->get("/api/v1/leave/{$leave->id}/certificate")->assertStatus(403);
    }

    public function test_leave_list_and_detail_endpoints(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $leave = LeaveRequest::create([
            'user_id'    => $employee->id,
            'created_by' => $employee->id,
            'type'       => 'leave',
            'start_date' => '2026-11-10',
            'end_date'   => '2026-11-10',
            'reason'     => 'Izin urusan perbankan.',
            'status'     => 'pending',
        ]);

        Sanctum::actingAs($employee, ['employee']);

        // List
        $resList = $this->getJson('/api/v1/leave');
        $resList->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        // Detail
        $resDetail = $this->getJson("/api/v1/leave/{$leave->id}");
        $resDetail->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $leave->id);
    }

    public function test_leave_review_approval_by_manager(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);

        // Karyawan buat pengajuan
        Sanctum::actingAs($employee, ['employee']);
        $res = $this->postJson('/api/v1/leave', [
            'type'       => 'leave',
            'start_date' => '2026-11-15',
            'end_date'   => '2026-11-16',
            'reason'     => 'Acara pernikahan adik kandung.',
        ]);
        $leaveId = $res->json('data.id');

        // Review oleh Manager
        Sanctum::actingAs($manager, ['manager']);
        $reviewRes = $this->postJson("/api/v1/leave/{$leaveId}/review", [
            'approved_dates' => ['2026-11-15', '2026-11-16'],
            'paid_dates'     => ['2026-11-15', '2026-11-16'],
            'review_note'    => 'Disetujui, selamat untuk keluarga.',
        ]);

        $reviewRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->assertEquals('approved', LeaveRequest::find($leaveId)->status);
    }

    public function test_leave_cancellation_by_employee(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        Sanctum::actingAs($employee, ['employee']);
        $res = $this->postJson('/api/v1/leave', [
            'type'       => 'leave',
            'start_date' => '2026-11-20',
            'end_date'   => '2026-11-20',
            'reason'     => 'Mau jalan-jalan tapi ragu.',
        ]);
        $leaveId = $res->json('data.id');

        $deleteRes = $this->deleteJson("/api/v1/leave/{$leaveId}");
        $deleteRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('leave_requests', ['id' => $leaveId]);
    }
}

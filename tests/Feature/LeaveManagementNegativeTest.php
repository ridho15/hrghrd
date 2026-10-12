<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveDay;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveManagementNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_submission_fails_when_notice_period_less_than_h_minus_7(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        // Submitting for 2 days from now (policy requires at least 7 days notice)
        $shortNoticeDate = now('Asia/Jakarta')->addDays(2)->toDateString();

        $response = $this->post('/leave', [
            'type' => 'leave',
            'start_date' => $shortNoticeDate,
            'end_date' => $shortNoticeDate,
            'reason' => 'Keperluan mendesak tanpa notice',
        ]);

        $response->assertSessionHasErrors('start_date');
        $this->assertDatabaseMissing('leave_requests', ['user_id' => $employee->id]);
    }

    public function test_sick_leave_submission_fails_without_medical_certificate(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $tomorrow = now('Asia/Jakarta')->addDay()->toDateString();

        $response = $this->post('/leave', [
            'type' => 'sick',
            'start_date' => $tomorrow,
            'end_date' => $tomorrow,
            'reason' => 'Sakit flu berat tanpa surat dokter',
            // Missing certificate file!
        ]);

        $response->assertSessionHasErrors('certificate');
        $this->assertDatabaseMissing('leave_requests', ['user_id' => $employee->id]);
    }

    public function test_leave_submission_fails_when_end_date_before_start_date(): void
    {
        $branch = $this->createBranch();
        $this->actingAsEmployee($branch);

        $start = now('Asia/Jakarta')->addDays(15)->toDateString();
        $end = now('Asia/Jakarta')->addDays(10)->toDateString(); // Inverted!

        $response = $this->post('/leave', [
            'type' => 'leave',
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'Tanggal terbalik',
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_leave_submission_is_rejected_on_overlapping_dates(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $date1 = now('Asia/Jakarta')->addDays(10)->toDateString();
        $date2 = now('Asia/Jakarta')->addDays(12)->toDateString();

        // Existing approved leave
        $existing = LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'leave',
            'start_date' => $date1,
            'end_date' => $date2,
            'reason' => 'Existing leave',
            'status' => 'approved',
            'created_by' => $employee->id,
        ]);
        LeaveDay::create(['leave_request_id' => $existing->id, 'date' => $date1, 'status' => 'approved', 'paid' => true]);
        LeaveDay::create(['leave_request_id' => $existing->id, 'date' => $date2, 'status' => 'approved', 'paid' => true]);

        // Attempting to submit another request overlapping date1
        $response = $this->post('/leave', [
            'type' => 'leave',
            'start_date' => $date1,
            'end_date' => now('Asia/Jakarta')->addDays(15)->toDateString(),
            'reason' => 'Overlapping request',
        ]);

        $response->assertStatus(409);
    }

    public function test_manager_is_forbidden_from_approving_own_leave_request(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);

        $targetDate = now('Asia/Jakarta')->addDays(10)->toDateString();

        // Leave request created by the manager
        $leave = LeaveRequest::create([
            'user_id' => $manager->id,
            'type' => 'leave',
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'reason' => 'Manager vacation',
            'status' => 'pending',
            'created_by' => $manager->id, // Created by manager!
        ]);
        LeaveDay::create(['leave_request_id' => $leave->id, 'date' => $targetDate, 'status' => 'pending', 'paid' => false]);

        // Manager tries to approve their own request
        $response = $this->post("/leave/{$leave->id}/review", [
            'approved_dates' => [$targetDate],
            'paid_dates' => [$targetDate],
            'review_note' => 'Self approving my own vacation',
        ]);

        $response->assertForbidden();
        $this->assertSame('pending', $leave->fresh()->status);
    }

    public function test_leave_review_fails_with_fictitious_dates_not_in_request(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $manager = $this->actingAsManager($branch);

        $dateReal = now('Asia/Jakarta')->addDays(10)->toDateString();
        $dateFake = now('Asia/Jakarta')->addDays(30)->toDateString();

        $leave = LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'leave',
            'start_date' => $dateReal,
            'end_date' => $dateReal,
            'reason' => 'Employee vacation',
            'status' => 'pending',
            'created_by' => $employee->id,
        ]);
        LeaveDay::create(['leave_request_id' => $leave->id, 'date' => $dateReal, 'status' => 'pending', 'paid' => false]);

        // Reviewer passes dateFake which was never requested
        $response = $this->post("/leave/{$leave->id}/review", [
            'approved_dates' => [$dateFake],
            'paid_dates' => [],
            'review_note' => 'Attempting review with invalid date',
        ]);

        $response->assertStatus(422);
    }

    public function test_certificate_download_returns_404_when_physical_file_is_missing(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $leave = LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'sick',
            'start_date' => now('Asia/Jakarta')->toDateString(),
            'end_date' => now('Asia/Jakarta')->toDateString(),
            'reason' => 'Demam tinggi',
            'status' => 'pending',
            'certificate_path' => 'certificates/missing_file.pdf',
            'certificate_name' => 'surat_dokter.pdf',
            'created_by' => $employee->id,
        ]);

        \Illuminate\Support\Facades\Storage::disk('local')->delete('certificates/missing_file.pdf');

        $response = $this->get("/leave/{$leave->id}/certificate");
        $response->assertStatus(404);
    }

    public function test_api_certificate_download_returns_404_json_when_physical_file_is_missing(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $leave = LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'sick',
            'start_date' => now('Asia/Jakarta')->toDateString(),
            'end_date' => now('Asia/Jakarta')->toDateString(),
            'reason' => 'Demam tinggi',
            'status' => 'pending',
            'certificate_path' => 'certificates/missing_api_file.pdf',
            'certificate_name' => 'surat_dokter.pdf',
            'created_by' => $employee->id,
        ]);

        \Illuminate\Support\Facades\Storage::disk('local')->delete('certificates/missing_api_file.pdf');

        \Laravel\Sanctum\Sanctum::actingAs($employee, ['employee']);
        $response = $this->getJson("/api/v1/leave/{$leave->id}/certificate");
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Berkas fisik surat keterangan tidak ditemukan di server penyimpanan.',
            ]);
    }

    public function test_certificate_endpoint_supports_inline_preview_response(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $fakeContent = '%PDF-1.4 Fake PDF Content';
        \Illuminate\Support\Facades\Storage::disk('local')->put('certificates/test_cert.pdf', $fakeContent);

        $leave = LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'sick',
            'start_date' => now('Asia/Jakarta')->toDateString(),
            'end_date' => now('Asia/Jakarta')->toDateString(),
            'reason' => 'Demam tinggi',
            'status' => 'pending',
            'certificate_path' => 'certificates/test_cert.pdf',
            'certificate_name' => 'surat_dokter.pdf',
            'created_by' => $employee->id,
        ]);

        $response = $this->get("/leave/{$leave->id}/certificate?inline=1");
        $response->assertStatus(200);
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }

    public function test_leave_submission_fails_when_reason_less_than_10_characters(): void
    {
        $branch = $this->createBranch();
        $this->actingAsEmployee($branch);

        $start = now('Asia/Jakarta')->addDays(10)->toDateString();

        $response = $this->post('/leave', [
            'type' => 'leave',
            'start_date' => $start,
            'end_date' => $start,
            'reason' => 'Pendek', // Only 6 characters (< 10)
        ]);

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_leave_service_throws_422_when_reason_less_than_10_characters(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $service = new \App\Services\LeaveService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Alasan pengajuan izin/cuti minimal 10 karakter.');

        $service->submit($employee, [
            'type' => 'leave',
            'start_date' => now('Asia/Jakarta')->addDays(10)->toDateString(),
            'end_date' => now('Asia/Jakarta')->addDays(10)->toDateString(),
            'reason' => 'Cuti saja', // 9 characters
        ]);
    }
}

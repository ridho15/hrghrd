<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_record_actor_id_and_action_accurately(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser(['branch_id' => $branch->id]);

        $targetDate = now('Asia/Jakarta')->addDays(10)->toDateString();

        // 1. Shift creation triggers audit log
        $this->post('/shifts', [
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'date' => $targetDate,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $log = AuditLog::where('subject_type', 'shift')->where('action', 'create')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->actor_id);

        // 2. Shift approval triggers audit log
        $shiftId = $log->subject_id;
        $this->post("/shifts/{$shiftId}/approve");

        $approveLog = AuditLog::where('subject_type', 'shift')->where('action', 'approve')->first();
        $this->assertNotNull($approveLog);
        $this->assertSame($admin->id, $approveLog->actor_id);
        $this->assertSame($shiftId, $approveLog->subject_id);
    }
}

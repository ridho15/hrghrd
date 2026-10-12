<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceException;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseOneQaFixesVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_with_attendance_cannot_be_deleted(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $user = $this->createUser(['branch_id' => $branch->id]);

        $shift = Shift::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'start_at' => now('Asia/Jakarta')->subHours(4),
            'end_at' => now('Asia/Jakarta')->addHours(4),
            'status' => 'approved',
            'version' => 1,
        ]);

        Attendance::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'checkin_at' => now('Asia/Jakarta')->subHours(4),
            'status' => 'present',
        ]);

        $response = $this->delete("/shifts/{$shift->id}");
        $response->assertSessionHasErrors('shift');

        // Pastikan shift dan absensi tetap utuh di database
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
        $this->assertDatabaseHas('attendances', ['shift_id' => $shift->id]);
    }

    public function test_shift_with_exception_ticket_cannot_be_deleted(): void
    {
        $branch = $this->createBranch();
        $this->actingAsManager($branch);
        $user = $this->createUser(['branch_id' => $branch->id]);

        $shift = Shift::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'start_at' => now('Asia/Jakarta')->subHours(4),
            'end_at' => now('Asia/Jakarta')->addHours(4),
            'status' => 'approved',
            'version' => 1,
        ]);

        AttendanceException::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'action' => 'in',
            'reason' => 'GPS glitch pada saat check-in',
            'status' => 'pending',
        ]);

        $response = $this->delete("/shifts/{$shift->id}");
        $response->assertSessionHasErrors('shift');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
        $this->assertDatabaseHas('attendance_exceptions', ['shift_id' => $shift->id]);
    }

    public function test_person_with_shifts_is_safely_deactivated_without_silent_shift_wipe(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $user = $this->createUser(['branch_id' => $branch->id, 'active' => true]);

        $shift = Shift::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'start_at' => now('Asia/Jakarta')->addDay(),
            'end_at' => now('Asia/Jakarta')->addDay()->addHours(8),
            'status' => 'draft',
            'version' => 1,
        ]);

        $response = $this->delete("/people/{$user->id}");
        $response->assertSessionHas('ok');

        // User dinonaktifkan secara aman, bukan dihapus
        $this->assertDatabaseHas('users', ['id' => $user->id, 'active' => false]);
        // Shift tidak terhapus diam-diam
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);

        // Audit log deaktivasi tercatat
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'action' => 'deactivate',
        ]);
    }

    public function test_audit_log_search_does_not_throw_500_for_any_keyword(): void
    {
        $admin = $this->actingAsAdmin();
        \App\Support\Audit::record('user', $admin->id, 'login', 'Masuk ke sistem');

        $keywords = ['Masuk', 'Admin', '123', '@example', 'random_query_xyz'];
        foreach ($keywords as $kw) {
            $response = $this->get('/audit?search=' . urlencode($kw));
            $response->assertOk();
            $response->assertViewIs('audit');
        }
    }

    public function test_shifts_branch_filter_properly_filters_shifts_by_branch_id(): void
    {
        $this->actingAsAdmin();
        $branchA = $this->createBranch(['name' => 'Cabang Alpha']);
        $branchB = $this->createBranch(['name' => 'Cabang Beta']);

        $userA = $this->createUser(['branch_id' => $branchA->id]);
        $userB = $this->createUser(['branch_id' => $branchB->id]);

        $shiftA = Shift::create([
            'user_id' => $userA->id,
            'branch_id' => $branchA->id,
            'start_at' => now('Asia/Jakarta')->addDays(2),
            'end_at' => now('Asia/Jakarta')->addDays(2)->addHours(8),
            'status' => 'draft',
            'version' => 1,
        ]);

        $shiftB = Shift::create([
            'user_id' => $userB->id,
            'branch_id' => $branchB->id,
            'start_at' => now('Asia/Jakarta')->addDays(2),
            'end_at' => now('Asia/Jakarta')->addDays(2)->addHours(8),
            'status' => 'draft',
            'version' => 1,
        ]);

        // Filter Cabang B
        $response = $this->get('/shifts?branch_id=' . $branchB->id);
        $response->assertOk();
        $response->assertSee($userB->name);
        $response->assertDontSee($userA->name);
    }

    public function test_single_shift_auto_approve_works_for_super_admin(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $user = $this->createUser(['branch_id' => $branch->id]);

        $response = $this->post('/shifts', [
            'mode' => 'single',
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'date' => now('Asia/Jakarta')->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'auto_approve' => 1,
        ]);

        $response->assertRedirect();
        $createdShift = Shift::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($createdShift);
        $this->assertEquals('approved', $createdShift->status);
        $this->assertEquals($admin->id, $createdShift->approved_by);
        $this->assertNotNull($createdShift->approved_at);
    }

    public function test_salary_changes_are_recorded_in_audit_logs(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $user = $this->createUser([
            'branch_id' => $branch->id,
            'base_salary' => 5000000,
        ]);

        $response = $this->post("/people/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'branch_id' => $branch->id,
            'position_id' => $user->position_id,
            'hired_at' => $user->hired_at->toDateString(),
            'base_salary' => 7500000,
            'active' => 1,
        ]);

        $response->assertRedirect();
        $latestAudit = AuditLog::where('subject_type', 'user')->where('subject_id', $user->id)->latest('id')->first();
        $this->assertNotNull($latestAudit);
        $this->assertStringContainsString('gaji pokok diperbarui', $latestAudit->reason);
        $this->assertStringContainsString('5.000.000', $latestAudit->reason);
        $this->assertStringContainsString('7.500.000', $latestAudit->reason);

        $before = json_decode($latestAudit->before, true);
        $after = json_decode($latestAudit->after, true);
        $this->assertEquals(5000000, $before['base_salary']);
        $this->assertEquals(7500000, $after['base_salary']);
    }

    public function test_settings_update_saves_before_and_after_snapshots_in_audit(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/settings', [
            'late_grace_minutes' => 20,
            'late_unit_minutes' => 15,
            'late_penalty_per_unit' => 15000,
            'late_reject_minutes' => 60,
            'checkin_early_minutes' => 60,
            'checkout_late_hours' => 6,
            'overtime_threshold_minutes' => 90,
            'overtime_max_daily_minutes' => 240,
            'min_work_duration_minutes' => 30,
            'leave_notice_days' => 7,
            'sick_paid_days_per_case' => 3,
            'daily_divisor' => 'calendar',
            'hourly_divisor' => '24',
        ]);

        $response->assertRedirect();
        $audit = AuditLog::where('subject_type', 'settings')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertNotNull($audit->before);
        $this->assertNotNull($audit->after);

        $before = json_decode($audit->before, true);
        $after = json_decode($audit->after, true);
        $this->assertArrayHasKey('late_grace_minutes', $before);
        $this->assertEquals(20, $after['late_grace_minutes']);
    }

    public function test_payroll_index_rejects_future_month_with_422(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();

        $response = $this->get('/payroll?branch_id=' . $branch->id . '&month=2030-01');
        $response->assertStatus(422);
    }

    public function test_leave_request_reason_requires_minimum_10_characters_on_api(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser(['branch_id' => $branch->id]);
        Sanctum::actingAs($user, ['employee']);

        // Alasan hanya 6 karakter (harus gagal validasi 422)
        $response = $this->postJson('/api/v1/leave', [
            'type' => 'sick',
            'start_date' => now('Asia/Jakarta')->addDays(3)->toDateString(),
            'end_date' => now('Asia/Jakarta')->addDays(3)->toDateString(),
            'reason' => 'Demam ',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        // Alasan >= 10 karakter berhasil lolos validasi
        $success = $this->postJson('/api/v1/leave', [
            'type' => 'sick',
            'start_date' => now('Asia/Jakarta')->addDays(4)->toDateString(),
            'end_date' => now('Asia/Jakarta')->addDays(4)->toDateString(),
            'reason' => 'Demam tinggi dan flu berat',
        ]);

        $success->assertStatus(201);
    }

    public function test_security_headers_are_present_on_web_responses(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_branch_creation_rejects_radius_under_50_meters(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/branches', [
            'code' => 'TINYRAD',
            'name' => 'Cabang Radius Terlalu Kecil',
            'latitude' => -6.175,
            'longitude' => 106.827,
            'radius_m' => 20, // Di bawah batas minimal 50m
        ]);

        $response->assertSessionHasErrors('radius_m');

        // 50m diterima
        $valid = $this->post('/branches', [
            'code' => 'OKRAD50',
            'name' => 'Cabang Radius Standar 50m',
            'latitude' => -6.175,
            'longitude' => 106.827,
            'radius_m' => 50,
        ]);

        $valid->assertRedirect();
        $this->assertDatabaseHas('branches', ['code' => 'OKRAD50', 'radius_m' => 50]);
    }
}

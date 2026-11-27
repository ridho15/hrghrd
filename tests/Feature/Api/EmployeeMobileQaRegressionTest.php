<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeMobileQaRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_shifts_and_attendance_history_month_filter_work_on_sqlite_and_validate_format(): void
    {
        $branch = Branch::factory()->create(['active' => true]);
        $user = User::factory()->create([
            'role'      => 'employee',
            'branch_id' => $branch->id,
            'active'    => true,
        ]);

        $febShift = Shift::factory()->create([
            'user_id'   => $user->id,
            'branch_id' => $branch->id,
            'start_at'  => '2026-02-15 08:00:00',
            'end_at'    => '2026-02-15 16:00:00',
            'status'    => 'approved',
        ]);

        $novShift = Shift::factory()->create([
            'user_id'   => $user->id,
            'branch_id' => $branch->id,
            'start_at'  => '2026-11-22 13:00:00',
            'end_at'    => '2026-11-22 21:00:00',
            'status'    => 'approved',
        ]);

        Attendance::create([
            'shift_id'     => $febShift->id,
            'user_id'      => $user->id,
            'checkin_at'   => '2026-02-15 08:00:00',
            'checkout_at'  => '2026-02-15 16:00:00',
            'status'       => 'present',
            'late_minutes' => 0,
            'late_units'   => 0,
        ]);

        Attendance::create([
            'shift_id'     => $novShift->id,
            'user_id'      => $user->id,
            'checkin_at'   => '2026-11-22 13:00:00',
            'checkout_at'  => '2026-11-22 21:00:00',
            'status'       => 'present',
            'late_minutes' => 0,
            'late_units'   => 0,
        ]);

        // Test February (also guards against 31st-day rollover bug) and November filtering
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shifts?month=2026-11')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $novShift->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shifts?month=2026-02')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $febShift->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/history?month=2026-11')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.shift_id', $novShift->id);

        // Invalid month format returns 422 rather than 500
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shifts?month=invalid')
            ->assertUnprocessable();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/history?month=2026-13')
            ->assertUnprocessable();
    }

    public function test_team_structure_is_same_manager_is_false_when_both_have_no_manager(): void
    {
        $branch = Branch::factory()->create(['active' => true]);

        $manager = User::factory()->create([
            'role'       => 'manager',
            'branch_id'  => $branch->id,
            'manager_id' => null,
            'active'     => true,
        ]);

        $employeeNoMgr = User::factory()->create([
            'role'       => 'employee',
            'branch_id'  => $branch->id,
            'manager_id' => null,
            'active'     => true,
        ]);

        $peerNoMgr = User::factory()->create([
            'role'       => 'employee',
            'branch_id'  => $branch->id,
            'manager_id' => null,
            'active'     => true,
        ]);

        $peerUnderMgr = User::factory()->create([
            'role'       => 'employee',
            'branch_id'  => $branch->id,
            'manager_id' => $manager->id,
            'active'     => true,
        ]);

        $res = $this->actingAs($employeeNoMgr, 'sanctum')
            ->getJson('/api/v1/profile/team')
            ->assertOk();

        $colleagues = collect($res->json('data.colleagues'));
        $this->assertFalse($colleagues->firstWhere('id', $manager->id)['is_same_manager']);
        $this->assertFalse($colleagues->firstWhere('id', $peerNoMgr->id)['is_same_manager']);
        $this->assertFalse($colleagues->firstWhere('id', $peerUnderMgr->id)['is_same_manager']);

        // Now assign real manager to employeeNoMgr and verify peerUnderMgr matches while peerNoMgr does not
        $employeeNoMgr->update(['manager_id' => $manager->id]);

        $resWithMgr = $this->actingAs($employeeNoMgr, 'sanctum')
            ->getJson('/api/v1/profile/team')
            ->assertOk();

        $colleaguesWithMgr = collect($resWithMgr->json('data.colleagues'));
        $this->assertTrue($colleaguesWithMgr->firstWhere('id', $peerUnderMgr->id)['is_same_manager']);
        $this->assertFalse($colleaguesWithMgr->firstWhere('id', $peerNoMgr->id)['is_same_manager']);
    }

    public function test_manager_shifts_and_history_with_mine_filter_returns_only_own_records(): void
    {
        $branch = Branch::factory()->create(['active' => true]);
        $manager = User::factory()->create([
            'role'      => 'manager',
            'branch_id' => $branch->id,
            'active'    => true,
        ]);
        $staff = User::factory()->create([
            'role'      => 'employee',
            'branch_id' => $branch->id,
            'active'    => true,
        ]);

        $managerShift = Shift::factory()->create([
            'user_id'   => $manager->id,
            'branch_id' => $branch->id,
            'start_at'  => '2026-11-27 08:00:00',
            'end_at'    => '2026-11-27 17:00:00',
            'status'    => 'approved',
        ]);
        $staffShift = Shift::factory()->create([
            'user_id'   => $staff->id,
            'branch_id' => $branch->id,
            'start_at'  => '2026-11-27 09:00:00',
            'end_at'    => '2026-11-27 18:00:00',
            'status'    => 'approved',
        ]);

        Attendance::create([
            'shift_id'     => $staffShift->id,
            'user_id'      => $staff->id,
            'checkin_at'   => '2026-11-27 09:00:00',
            'status'       => 'present',
            'late_minutes' => 0,
            'late_units'   => 0,
        ]);

        // Tanpa mine=1 (untuk manajemen cabang), manager melihat semua shift & history cabang
        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/shifts?month=2026-11')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/attendance/history?month=2026-11')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Dengan mine=1 (layar jadwal & riwayat pribadi mobile), manager hanya melihat datanya sendiri
        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/shifts?month=2026-11&mine=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $managerShift->id);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/attendance/history?month=2026-11&mine=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

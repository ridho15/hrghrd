<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiShiftResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_today_prioritizes_active_and_upcoming_afternoon_shift_over_completed_morning_shift(): void
    {
        Carbon::setTestNow('2026-11-23 12:45:00');

        $branch = Branch::factory()->create([
            'name'      => 'Cabang Pusat',
            'latitude'  => -6.2088,
            'longitude' => 106.8456,
            'radius_m'  => 100,
            'active'    => true,
        ]);

        $user = User::factory()->create([
            'role'      => 'employee',
            'branch_id' => $branch->id,
            'active'    => true,
        ]);

        // Shift 1 (Pagi 08:00 - 12:00) yang sudah tuntas (checkin & checkout)
        $morningShift = Shift::factory()->create([
            'user_id'     => $user->id,
            'branch_id'   => $branch->id,
            'start_at'    => '2026-11-23 08:00:00',
            'end_at'      => '2026-11-23 12:00:00',
            'status'      => 'approved',
        ]);

        Attendance::create([
            'shift_id'    => $morningShift->id,
            'user_id'     => $user->id,
            'checkin_at'  => '2026-11-23 07:55:00',
            'checkout_at' => '2026-11-23 12:05:00',
            'status'      => 'present',
            'late_minutes'=> 0,
            'late_units'  => 0,
        ]);

        // Shift 2 (Siang 13:00 - 21:00) yang belum check-in
        $afternoonShift = Shift::factory()->create([
            'user_id'     => $user->id,
            'branch_id'   => $branch->id,
            'start_at'    => '2026-11-23 13:00:00',
            'end_at'      => '2026-11-23 21:00:00',
            'status'      => 'approved',
        ]);

        // Panggil endpoint /api/v1/profile/today
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/profile/today');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_shift', true)
            ->assertJsonPath('data.shift.id', $afternoonShift->id)
            ->assertJsonPath('data.status', 'not_checked_in');

        Carbon::setTestNow();
    }

    public function test_profile_today_ignores_stale_unclosed_shift_from_previous_days(): void
    {
        Carbon::setTestNow('2026-11-27 08:30:00');

        $branch = Branch::factory()->create(['active' => true]);
        $user = User::factory()->create([
            'role'      => 'employee',
            'branch_id' => $branch->id,
            'active'    => true,
        ]);

        // Shift 3 hari lalu yang lupa check-out (checkout_at = null)
        $staleShift = Shift::factory()->create([
            'user_id'   => $user->id,
            'branch_id' => $branch->id,
            'start_at'  => '2026-11-24 08:00:00',
            'end_at'    => '2026-11-24 17:00:00',
            'status'    => 'approved',
        ]);

        Attendance::create([
            'shift_id'     => $staleShift->id,
            'user_id'      => $user->id,
            'checkin_at'   => '2026-11-24 08:01:00',
            'checkout_at'  => null,
            'status'       => 'present',
            'late_minutes' => 0,
            'late_units'   => 0,
        ]);

        // Shift hari ini yang belum pernah diabsen
        $todayShift = Shift::factory()->create([
            'user_id'   => $user->id,
            'branch_id' => $branch->id,
            'start_at'  => '2026-11-27 09:00:00',
            'end_at'    => '2026-11-27 18:00:00',
            'status'    => 'approved',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/profile/today');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_shift', true)
            ->assertJsonPath('data.shift.id', $todayShift->id)
            ->assertJsonPath('data.attendance', null)
            ->assertJsonPath('data.status', 'not_checked_in');

        Carbon::setTestNow();
    }

    public function test_profile_today_preserves_overnight_active_shift_within_window(): void
    {
        Carbon::setTestNow('2026-11-27 06:30:00');

        $branch = Branch::factory()->create(['active' => true]);
        $user = User::factory()->create([
            'role'      => 'employee',
            'branch_id' => $branch->id,
            'active'    => true,
        ]);

        // Shift malam lintas hari (22:00 kemarin s/d 06:00 hari ini) masih dalam jendela checkout
        $nightShift = Shift::factory()->create([
            'user_id'   => $user->id,
            'branch_id' => $branch->id,
            'start_at'  => '2026-11-26 22:00:00',
            'end_at'    => '2026-11-27 06:00:00',
            'status'    => 'approved',
        ]);

        Attendance::create([
            'shift_id'     => $nightShift->id,
            'user_id'      => $user->id,
            'checkin_at'   => '2026-11-26 22:00:00',
            'checkout_at'  => null,
            'status'       => 'present',
            'late_minutes' => 0,
            'late_units'   => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/profile/today');

        $response->assertOk()
            ->assertJsonPath('data.shift.id', $nightShift->id)
            ->assertJsonPath('data.status', 'checked_in');

        Carbon::setTestNow();
    }
}

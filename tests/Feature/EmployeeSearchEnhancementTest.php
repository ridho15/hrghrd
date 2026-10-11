<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSearchEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_search_trims_whitespace_and_matches_name(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $user1 = $this->createUser([
            'name' => 'Agus Pratama',
            'email' => 'agus@example.com',
            'username' => 'agus_jkt',
            'branch_id' => $branch->id,
        ]);
        $user2 = $this->createUser([
            'name' => 'Siti Rahma',
            'email' => 'siti@example.com',
            'username' => 'siti_jkt',
            'branch_id' => $branch->id,
        ]);

        // Search with trailing & leading whitespace
        $response = $this->get('/people?search=' . urlencode('   Agus   '));

        $response->assertOk();
        $response->assertSee('Agus Pratama');
        $response->assertDontSee('Siti Rahma');
    }

    public function test_people_search_matches_username_with_and_without_at_symbol(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $user = $this->createUser([
            'name' => 'Bambang Sudirman',
            'email' => 'bambang@example.com',
            'username' => 'bambang_hq',
            'branch_id' => $branch->id,
        ]);

        // Search by raw username
        $response1 = $this->get('/people?search=bambang_hq');
        $response1->assertOk();
        $response1->assertSee('Bambang Sudirman');

        // Search with @ handle
        $response2 = $this->get('/people?search=@bambang_hq');
        $response2->assertOk();
        $response2->assertSee('Bambang Sudirman');
    }

    public function test_shifts_search_matches_trimmed_name_and_username(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $user = $this->createUser([
            'name' => 'Dewi Lestari',
            'username' => 'dewi_shift',
            'branch_id' => $branch->id,
        ]);

        $month = now('Asia/Jakarta')->format('Y-m');
        Shift::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'start_at' => Carbon::now('Asia/Jakarta')->startOfMonth()->setTime(8, 0),
            'end_at' => Carbon::now('Asia/Jakarta')->startOfMonth()->setTime(17, 0),
        ]);

        // Search with space in name
        $response = $this->get('/shifts?month=' . $month . '&search=' . urlencode(' Dewi '));
        $response->assertOk();
        $response->assertSee('Dewi Lestari');

        // Search with username
        $responseUser = $this->get('/shifts?month=' . $month . '&search=@dewi_shift');
        $responseUser->assertOk();
        $responseUser->assertSee('Dewi Lestari');
    }

    public function test_api_employees_search_supports_whitespace_and_username(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $user = $this->createUser([
            'name' => 'Rian Hidayat',
            'email' => 'rian@example.com',
            'username' => 'rian_dev',
            'branch_id' => $branch->id,
        ]);

        // API search with space
        $response1 = $this->actingAs($admin)->getJson('/api/v1/admin/employees?search=' . urlencode('  Rian  '));
        $response1->assertOk();
        $response1->assertJsonFragment(['name' => 'Rian Hidayat']);

        // API search with @username
        $response2 = $this->actingAs($admin)->getJson('/api/v1/admin/employees?search=@rian_dev');
        $response2->assertOk();
        $response2->assertJsonFragment(['name' => 'Rian Hidayat']);
    }

    public function test_leave_index_search_supports_whitespace_and_username(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $user = $this->createUser([
            'name' => 'Cynthia Bella',
            'username' => 'cynthia_bell',
            'branch_id' => $branch->id,
        ]);

        $date = now('Asia/Jakarta')->addDays(10)->toDateString();
        LeaveRequest::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'start_date' => $date,
            'end_date' => $date,
            'reason' => 'Keperluan keluarga penting',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        // Search by username
        $response = $this->get('/leave?search=@cynthia_bell');
        $response->assertOk();
        $response->assertSee('Cynthia Bella');
        $response->assertSee('Keperluan keluarga penting');
    }

    public function test_people_search_with_empty_status_parameter_finds_active_users(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $user = $this->createUser([
            'name' => 'Agus Setiawan',
            'email' => 'agus.setiawan@example.com',
            'username' => 'agussetiawan_jktpst',
            'branch_id' => $branch->id,
            'active' => true,
        ]);

        // Simulating the exact form submission URL with empty query parameters
        $response = $this->get('/people?search=agus+setiawan&branch_id=&position_id=&role=&status=');

        $response->assertOk();
        $response->assertSee('Agus Setiawan');
        $response->assertDontSee('Tidak ada data karyawan yang cocok dengan kriteria pencarian');
    }
}

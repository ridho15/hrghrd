<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Position;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NewFeaturesAndFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_using_username(): void
    {
        $user = $this->createUser([
            'username' => 'ahmad.zaki',
            'email' => 'ahmad@example.com',
            'password' => Hash::make('SecretPassword123!'),
            'active' => true,
        ]);

        $response = $this->post('/login', [
            'login' => 'ahmad.zaki',
            'password' => 'SecretPassword123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_using_email_on_same_field(): void
    {
        $user = $this->createUser([
            'username' => 'sri.wahyuni',
            'email' => 'sri@example.com',
            'password' => Hash::make('SecretPassword123!'),
            'active' => true,
        ]);

        $response = $this->post('/login', [
            'login' => 'sri@example.com',
            'password' => 'SecretPassword123!',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_dedicated_today_attendance_page_is_accessible(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $shift = $this->createShift($employee, $branch, [
            'start_at' => now('Asia/Jakarta')->setTime(8, 0)->toDateTimeString(),
            'end_at' => now('Asia/Jakarta')->setTime(17, 0)->toDateTimeString(),
        ]);

        $response = $this->get('/attendance/today');

        $response->assertOk();
        $response->assertSee('Presensi Kerja Hari Ini');
        $response->assertSee($branch->name);
        $response->assertSee('Kode Cabang 8 Karakter');
    }

    public function test_admin_can_store_employee_with_bank_details_and_username(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $response = $this->post('/people', [
            'name' => 'Karyawan Baru Bank Test',
            'username' => 'karyawan.bank',
            'email' => 'karyawan.bank@example.com',
            'password' => 'PasswordSecure123!',
            'role' => 'employee',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'hired_at' => '2026-10-01',
            'base_salary' => 4500000,
            'bank_name' => 'BCA',
            'bank_account_number' => '5540864928',
            'bank_account_name' => 'Ahmad Zaki Yamani',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'karyawan.bank@example.com',
            'username' => 'karyawan.bank',
            'bank_name' => 'BCA',
            'bank_account_number' => '5540864928',
            'bank_account_name' => 'Ahmad Zaki Yamani',
        ]);
    }

    public function test_admin_can_update_employee_bank_details(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'bank_name' => null,
            'bank_account_number' => null,
        ]);

        $response = $this->post("/people/{$employee->id}", [
            'name' => $employee->name,
            'username' => 'updated.username',
            'email' => $employee->email,
            'role' => 'employee',
            'branch_id' => $branch->id,
            'position_id' => $employee->position_id,
            'hired_at' => '2026-10-01',
            'base_salary' => 4500000,
            'active' => 1,
            'bank_name' => 'BCA',
            'bank_account_number' => '0680117845',
            'bank_account_name' => 'Sri Wahyuni Lestari',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'username' => 'updated.username',
            'bank_name' => 'BCA',
            'bank_account_number' => '0680117845',
            'bank_account_name' => 'Sri Wahyuni Lestari',
        ]);
    }
}

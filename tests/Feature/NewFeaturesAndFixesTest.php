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

    public function test_admin_can_update_own_profile_without_demotion_or_missing_active_error(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->post("/people/{$admin->id}", [
            'name' => 'Super Administrator Updated',
            'username' => 'superadmin.updated',
            'email' => $admin->email,
            'branch_id' => $admin->branch_id,
            'position_id' => $admin->position_id,
            'hired_at' => '2026-01-01',
            'base_salary' => 10000000,
            // active and role can be omitted or passed, prepareForValidation will safely protect admin
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/people/{$admin->id}");

        $admin->refresh();
        $this->assertEquals('Super Administrator Updated', $admin->name);
        $this->assertEquals('superadmin.updated', $admin->username);
        $this->assertEquals('admin', $admin->role);
        $this->assertTrue((bool) $admin->active);
    }

    public function test_admin_can_deactivate_and_activate_employee(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id' => $branch->id,
            'active' => true,
        ]);

        $response = $this->post("/people/{$employee->id}", [
            'name' => $employee->name,
            'username' => $employee->username,
            'email' => $employee->email,
            'role' => 'employee',
            'branch_id' => $branch->id,
            'position_id' => $employee->position_id,
            'hired_at' => '2026-01-01',
            'base_salary' => 5000000,
            'active' => '0',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'active' => false,
        ]);
    }

    public function test_qr_kiosk_page_is_accessible_by_admin_and_supports_branch_parameter(): void
    {
        $this->actingAsAdmin();
        $branch1 = $this->createBranch(['name' => 'Cabang Sudirman', 'code' => 'SDR01']);
        $branch2 = $this->createBranch(['name' => 'Cabang Thamrin', 'code' => 'THM02']);

        // Default branch
        $responseDefault = $this->get('/branch-qr');
        $responseDefault->assertOk();
        $responseDefault->assertSee('QR Presensi Cabang');

        // Selected branch query
        $responseSpecific = $this->get("/branch-qr?branch_id={$branch2->id}");
        $responseSpecific->assertOk();
        $responseSpecific->assertSee('Cabang Thamrin');
        $responseSpecific->assertSee('THM02');
    }

    public function test_admin_can_reset_single_employee_password_to_default(): void
    {
        $this->actingAsAdmin();
        $employee = $this->createUser([
            'email' => 'karyawan.reset@example.com',
            'username' => 'karyawan.reset',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->post("/people/{$employee->id}/reset-password");

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('ok');

        $employee->refresh();
        $this->assertTrue(Hash::check('12345678', $employee->password));

        // Verifikasi login dengan password baru
        $this->post('/logout');
        $loginResponse = $this->post('/login', [
            'login' => 'karyawan.reset',
            'password' => '12345678',
        ]);
        $loginResponse->assertRedirect('/');
        $this->assertAuthenticatedAs($employee);

        // Verifikasi audit log
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'user',
            'subject_id' => $employee->id,
            'action' => 'reset_password',
        ]);
    }

    public function test_admin_cannot_reset_own_password_via_employee_reset_endpoint(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->post("/people/{$admin->id}/reset-password");

        $response->assertSessionHasErrors(['person']);
    }

    public function test_admin_can_bulk_reset_employee_passwords(): void
    {
        $admin = $this->actingAsAdmin();
        $adminOriginalPassword = $admin->password;

        $e1 = $this->createUser(['password' => Hash::make('Pass1!')]);
        $e2 = $this->createUser(['password' => Hash::make('Pass2!')]);
        $e3 = $this->createUser(['password' => Hash::make('Pass3!')]);

        $response = $this->post('/people/bulk-reset-password', [
            'user_ids' => [$e1->id, $e2->id, $e3->id, $admin->id],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('ok');

        $e1->refresh();
        $e2->refresh();
        $e3->refresh();
        $admin->refresh();

        $this->assertTrue(Hash::check('12345678', $e1->password));
        $this->assertTrue(Hash::check('12345678', $e2->password));
        $this->assertTrue(Hash::check('12345678', $e3->password));

        // Password admin harus tetap aman dan tidak diubah
        $this->assertEquals($adminOriginalPassword, $admin->password);

        // Verifikasi 3 catatan audit log tercatat
        $this->assertEquals(3, \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('subject_type', 'user')
            ->where('action', 'reset_password')
            ->whereIn('subject_id', [$e1->id, $e2->id, $e3->id])
            ->count());
    }

    public function test_non_admin_cannot_access_reset_password_endpoints(): void
    {
        $this->actingAsEmployee();
        $target = $this->createUser();

        $respSingle = $this->post("/people/{$target->id}/reset-password");
        $respSingle->assertForbidden();

        $respBulk = $this->post('/people/bulk-reset-password', [
            'user_ids' => [$target->id],
        ]);
        $respBulk->assertForbidden();
    }
}

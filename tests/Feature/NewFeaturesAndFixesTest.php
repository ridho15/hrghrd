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
        $this->assertTrue($employee->must_change_password);

        // Verifikasi login dengan password reset: diarahkan ke form wajib update password
        $this->post('/logout');
        $loginResponse = $this->post('/login', [
            'login' => 'karyawan.reset',
            'password' => '12345678',
        ]);
        $loginResponse->assertRedirect(route('password.force_change'));
        $this->assertAuthenticatedAs($employee);

        // Akses rute dashboard dicegat middleware EnsurePasswordChanged
        $dashboardResponse = $this->get('/');
        $dashboardResponse->assertRedirect(route('password.force_change'));

        // Form force change view terbuka normal
        $forceViewResponse = $this->get('/password/force-change');
        $forceViewResponse->assertOk();
        $forceViewResponse->assertSee('Wajib Perbarui Kata Sandi');

        // Melakukan update password baru
        $updateResponse = $this->post('/password/force-change', [
            'current_password' => '12345678',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
        $updateResponse->assertRedirect(route('home'));
        $updateResponse->assertSessionHas('ok');

        $employee->refresh();
        $this->assertFalse($employee->must_change_password);
        $this->assertTrue(Hash::check('NewPassword123!', $employee->password));

        // Verifikasi akses normal terbuka kembali
        $homeAfter = $this->get('/');
        $homeAfter->assertOk();

        // Verifikasi audit log
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'user',
            'subject_id' => $employee->id,
            'action' => 'reset_password',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'user',
            'subject_id' => $employee->id,
            'action' => 'force_password_change',
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

        $this->assertTrue($e1->must_change_password);
        $this->assertTrue($e2->must_change_password);
        $this->assertTrue($e3->must_change_password);

        // Password admin harus tetap aman dan tidak diubah
        $this->assertEquals($adminOriginalPassword, $admin->password);
        $this->assertFalse($admin->must_change_password);

        // Verifikasi 3 catatan audit log tercatat
        $this->assertEquals(3, \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('subject_type', 'user')
            ->where('action', 'reset_password')
            ->whereIn('subject_id', [$e1->id, $e2->id, $e3->id])
            ->count());
    }

    public function test_force_password_change_rejects_reusing_current_default_password(): void
    {
        $employee = $this->createUser([
            'password' => Hash::make('12345678'),
            'must_change_password' => true,
        ]);
        $this->actingAs($employee);

        // Submit dengan password baru sama dengan default 12345678
        $response = $this->post('/password/force-change', [
            'current_password' => '12345678',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);

        $response->assertSessionHasErrors(['password']);
        $employee->refresh();
        $this->assertTrue($employee->must_change_password);
    }

    public function test_admin_can_generate_username_for_single_employee(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch(['code' => 'SBY01', 'name' => 'Cabang Surabaya']);
        $employee = $this->createUser([
            'name' => 'Ahmad Zaki Pratama',
            'branch_id' => $branch->id,
            'username' => null,
        ]);

        $response = $this->post("/people/{$employee->id}/generate-username");
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('ok');

        $employee->refresh();
        $this->assertEquals('ahmadzakipratama_sby01', $employee->username);

        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'user',
            'subject_id' => $employee->id,
            'action' => 'generate_username',
        ]);
    }

    public function test_generate_username_handles_duplicate_collision_gracefully(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch(['code' => 'DEMO', 'name' => 'Cabang Demo']);
        
        $e1 = $this->createUser([
            'name' => 'Budi Santoso',
            'branch_id' => $branch->id,
            'username' => 'budisantoso_demo',
        ]);

        $e2 = $this->createUser([
            'name' => 'Budi Santoso',
            'branch_id' => $branch->id,
            'username' => null,
        ]);

        $response = $this->post("/people/{$e2->id}/generate-username");
        $response->assertSessionHasNoErrors();

        $e2->refresh();
        $this->assertEquals('budisantoso_demo_2', $e2->username);

        // Jika ada karyawan ketiga dengan nama sama
        $e3 = $this->createUser([
            'name' => 'Budi Santoso',
            'branch_id' => $branch->id,
            'username' => null,
        ]);

        $this->post("/people/{$e3->id}/generate-username");
        $e3->refresh();
        $this->assertEquals('budisantoso_demo_3', $e3->username);
    }

    public function test_admin_can_bulk_generate_usernames(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch(['code' => 'BDG01', 'name' => 'Cabang Bandung']);

        $e1 = $this->createUser(['name' => 'Dewi Lestari', 'branch_id' => $branch->id, 'username' => null]);
        $e2 = $this->createUser(['name' => 'Rian Hidayat', 'branch_id' => $branch->id, 'username' => null]);
        $e3 = $this->createUser(['name' => 'Admin Pusat', 'branch_id' => null, 'username' => null]);

        $response = $this->post('/people/bulk-generate-username', [
            'user_ids' => [$e1->id, $e2->id, $e3->id],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('ok');

        $e1->refresh();
        $e2->refresh();
        $e3->refresh();

        $this->assertEquals('dewilestari_bdg01', $e1->username);
        $this->assertEquals('rianhidayat_bdg01', $e2->username);
        $this->assertEquals('adminpusat_pusat', $e3->username);
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

    public function test_non_admin_cannot_access_generate_username_endpoints(): void
    {
        $this->actingAsEmployee();
        $target = $this->createUser();

        $respSingle = $this->post("/people/{$target->id}/generate-username");
        $respSingle->assertForbidden();

        $respBulk = $this->post('/people/bulk-generate-username', [
            'user_ids' => [$target->id],
        ]);
        $respBulk->assertForbidden();
    }

    public function test_people_list_page_displays_generate_username_button_and_bulk_toolbar(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch(['code' => 'DEMO']);
        $employee = $this->createUser([
            'name' => 'Citra Kirana',
            'branch_id' => $branch->id,
            'username' => 'citrakirana_demo',
        ]);

        $response = $this->get('/people');
        $response->assertOk();

        // Verifikasi badge username tampil
        $response->assertSee($employee->username);
        $response->assertSee('&#64;' . $employee->username, false);

        // Verifikasi tombol single generate username per row ada di DOM
        $response->assertSee(route('people.generate_username', $employee->id));
        $response->assertSee('Generate / Reset Username (namakaryawan_cabang)');

        // Verifikasi tombol bulk action di floating toolbar
        $response->assertSee(route('people.bulk_generate_username'));
        $response->assertSee('btn-trigger-bulk-generate-username');
        $response->assertSee('Generate Username');
    }

    public function test_username_generation_with_special_characters_and_accents(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch(['code' => 'JKT-PST', 'name' => 'Jakarta Pusat']);
        $employee = $this->createUser([
            'name' => "Dr. M. Syafi'i, S.Kom.",
            'branch_id' => $branch->id,
            'username' => null,
        ]);

        $response = $this->post("/people/{$employee->id}/generate-username");
        $response->assertSessionHasNoErrors();

        $employee->refresh();
        // Karakter non-alfanumerik dibersihkan, huruf kecil
        $this->assertEquals('drmsyafiiskom_jktpst', $employee->username);
    }

    public function test_employee_must_change_password_cannot_access_leave_or_attendance_pages(): void
    {
        $employee = $this->createUser([
            'password' => Hash::make('12345678'),
            'must_change_password' => true,
        ]);
        $this->actingAs($employee);

        // Uji coba akses ke halaman cuti
        $respLeave = $this->get('/leave');
        $respLeave->assertRedirect(route('password.force_change'));

        // Uji coba akses ke halaman absensi
        $respAttendance = $this->get('/attendance/today');
        $respAttendance->assertRedirect(route('password.force_change'));

        // Uji coba akses ke halaman profil karyawan
        $respShow = $this->get("/people/{$employee->id}");
        $respShow->assertRedirect(route('password.force_change'));
    }

    public function test_employee_must_change_password_can_logout(): void
    {
        $employee = $this->createUser([
            'password' => Hash::make('12345678'),
            'must_change_password' => true,
        ]);
        $this->actingAs($employee);

        $response = $this->post('/logout');
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_with_default_password_is_intercepted_even_if_flag_was_false(): void
    {
        // Simulasi akun yang ber-password 12345678 namun flag must_change_password bernilai false (misal akun historis)
        $employee = $this->createUser([
            'email' => 'historis@example.test',
            'username' => 'historis_demo',
            'password' => Hash::make('12345678'),
            'must_change_password' => false,
        ]);

        // Saat login, sistem langsung redirect ke /password/force-change
        $loginResponse = $this->post('/login', [
            'login' => 'historis@example.test',
            'password' => '12345678',
        ]);
        $loginResponse->assertRedirect(route('password.force_change'));
        $this->assertAuthenticatedAs($employee);

        // Saat mencoba akses Beranda (/), dicegat oleh middleware EnsurePasswordChanged
        $dashboardResponse = $this->get('/');
        $dashboardResponse->assertRedirect(route('password.force_change'));

        // View form force change terbuka normal
        $forceView = $this->get('/password/force-change');
        $forceView->assertOk();
        $forceView->assertSee('Wajib Perbarui Kata Sandi');

        // Melakukan update ke password baru
        $updateResponse = $this->post('/password/force-change', [
            'current_password' => '12345678',
            'password' => 'SandiBaruAman123!',
            'password_confirmation' => 'SandiBaruAman123!',
        ]);
        $updateResponse->assertRedirect(route('home'));
        $updateResponse->assertSessionHas('ok');

        $employee->refresh();
        $this->assertFalse($employee->must_change_password);
        $this->assertTrue(Hash::check('SandiBaruAman123!', $employee->password));

        // Setelah ganti password baru, akses ke Beranda terbuka penuh
        $homeAfter = $this->get('/');
        $homeAfter->assertOk();
    }
}

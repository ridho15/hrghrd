<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndRbacNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_fails_with_invalid_password(): void
    {
        $user = $this->createUser([
            'email' => 'active@example.test',
            'password' => Hash::make('CorrectPassword123!'),
            'active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'active@example.test',
            'password' => 'WrongPassword!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rejected_for_deactivated_account(): void
    {
        $this->createUser([
            'email' => 'deactivated@example.test',
            'password' => Hash::make('ValidPassword123!'),
            'active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'deactivated@example.test',
            'password' => 'ValidPassword123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unauthenticated_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/people')->assertRedirect('/login');
        $this->get('/shifts')->assertRedirect('/login');
        $this->get('/leave')->assertRedirect('/login');
        $this->get('/payroll')->assertRedirect('/login');
    }

    public function test_employee_is_forbidden_from_accessing_admin_endpoints(): void
    {
        $this->actingAsEmployee();

        $this->get('/people')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->get('/payroll')->assertForbidden();
        $this->get('/import')->assertForbidden();
        $this->post('/branches', ['name' => 'Bad Branch', 'code' => 'BAD', 'latitude' => 0, 'longitude' => 0, 'radius_m' => 100])->assertForbidden();
    }

    public function test_manager_is_forbidden_from_payroll_and_system_settings(): void
    {
        $this->actingAsManager();

        $this->get('/payroll')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->post('/settings', ['daily_divisor' => 'calendar', 'hourly_divisor' => 24])->assertForbidden();
    }

    public function test_manager_is_forbidden_from_cross_branch_action(): void
    {
        $branchA = $this->createBranch(['name' => 'Branch Alpha']);
        $branchB = $this->createBranch(['name' => 'Branch Beta']);

        // Manager of Branch A
        $this->actingAsManager($branchA);

        // Attempts to approve shift belonging to Branch B
        $employeeB = $this->createUser(['role' => 'employee', 'branch_id' => $branchB->id]);
        $shiftB = $this->createShift($employeeB, $branchB, ['status' => 'draft']);

        $response = $this->post("/shifts/{$shiftB->id}/approve");
        $response->assertForbidden();
        $this->assertSame('draft', $shiftB->fresh()->status);
    }

    public function test_employee_is_forbidden_from_viewing_other_employees_medical_certificate(): void
    {
        $branch = $this->createBranch();
        $employeeA = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $employeeB = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $leaveB = LeaveRequest::create([
            'user_id' => $employeeB->id,
            'type' => 'sick',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'reason' => 'Demam berdarah',
            'certificate_path' => 'certificates/fake.pdf',
            'status' => 'approved',
            'created_by' => $employeeB->id,
        ]);

        // Employee A tries to view Employee B's certificate
        $this->actingAs($employeeA);
        $response = $this->get("/leave/{$leaveB->id}/certificate");
        $response->assertForbidden();
    }

    public function test_employee_slip_view_does_not_contain_forbidden_rekap_link(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $this->actingAs($employee);
        $response = $this->get("/payroll/slip/{$employee->id}?month=2026-09&branch_id={$branch->id}");
        $response->assertOk();
        $response->assertDontSee('Kembali ke Rekap');
        $response->assertSee('Kembali ke Profil');
        $response->assertSee(route('people.show', $employee->id));
    }

    public function test_manager_profile_view_does_not_link_to_forbidden_people_index(): void
    {
        $branch = $this->createBranch();
        $manager = $this->actingAsManager($branch);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        $response = $this->get("/people/{$employee->id}");
        $response->assertOk();
        $response->assertDontSee('Kembali ke Direktori Karyawan');
        $response->assertSee('Kembali ke Beranda');
        $response->assertSee(route('home'));
    }

    public function test_employee_profile_view_contains_link_to_own_slip_gaji(): void
    {
        $branch = $this->createBranch();
        $employee = $this->actingAsEmployee($branch);

        $response = $this->get("/people/{$employee->id}");
        $response->assertOk();
        $response->assertSee('Lihat Slip Gaji Bulan Ini');
        $response->assertSee('/payroll/slip/' . $employee->id);
    }
}

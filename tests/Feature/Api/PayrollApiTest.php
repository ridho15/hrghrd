<?php

namespace Tests\Feature\Api;

use App\Models\PayrollRun;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_slip_endpoint_returns_json_breakdown(): void
    {
        $branch = $this->createBranch();
        $employee = $this->createUser([
            'branch_id'   => $branch->id,
            'base_salary' => 6000000,
            'hired_at'    => '2026-01-01',
        ]);

        Sanctum::actingAs($employee, ['employee']);

        $response = $this->getJson('/api/v1/payroll/slip?month=2026-10');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'ref_number',
                    'period',
                    'employee' => ['id', 'name', 'email'],
                    'branch'   => ['id', 'name', 'code'],
                    'employment' => ['monthly_salary', 'daily_rate', 'hourly_rate'],
                    'earnings'   => ['prorated_base', 'gross_total'],
                    'deductions' => ['unpaid_deduction', 'late_deduction', 'total_deductions'],
                    'net_pay',
                ],
            ]);

        $this->assertEquals(6000000, $response->json('data.employment.monthly_salary'));
    }

    public function test_employee_cannot_view_other_employee_slip(): void
    {
        $branch = $this->createBranch();
        $employee1 = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $employee2 = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        Sanctum::actingAs($employee1, ['employee']);

        // Employee 1 attempts to view Employee 2's slip
        $response = $this->getJson("/api/v1/payroll/slip/{$employee2->id}?month=2026-10");
        $response->assertStatus(403);
    }

    public function test_manager_can_view_branch_employee_slip(): void
    {
        $branch = $this->createBranch();
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        Sanctum::actingAs($manager, ['manager']);

        $response = $this->getJson("/api/v1/payroll/slip/{$employee->id}?month=2026-10");
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee.id', $employee->id);
    }

    public function test_payroll_summary_for_manager(): void
    {
        $branch = $this->createBranch();
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->createUser(['role' => 'employee', 'branch_id' => $branch->id, 'base_salary' => 5000000]);

        Sanctum::actingAs($manager, ['manager']);

        $response = $this->getJson("/api/v1/payroll/summary?month=2026-10&branch_id={$branch->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'branch' => ['id', 'name', 'code'],
                    'month',
                    'status',
                    'summary' => ['total_base', 'total_overtime', 'total_deductions', 'total_net', 'employee_count'],
                    'blockers_count',
                    'blockers',
                ],
            ]);
    }

    public function test_admin_and_manager_master_data_endpoints(): void
    {
        $branch = $this->createBranch();
        $admin = $this->createUser(['role' => 'admin', 'branch_id' => null]);
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);

        // Manager views employees
        Sanctum::actingAs($manager, ['manager']);
        $resEmp = $this->getJson('/api/v1/admin/employees');
        $resEmp->assertStatus(200)
            ->assertJsonPath('success', true);

        // Manager views branches
        $resBranch = $this->getJson('/api/v1/admin/branches');
        $resBranch->assertStatus(200)
            ->assertJsonPath('success', true);

        // Admin views all branches
        Sanctum::actingAs($admin, ['admin']);
        $resAdminBranch = $this->getJson('/api/v1/admin/branches');
        $resAdminBranch->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}

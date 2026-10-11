<?php

namespace Tests\Feature;

use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftBranchAutoSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_shift_automatically_aligns_branch_to_employee_branch(): void
    {
        $this->actingAsAdmin();

        $branchA = $this->createBranch(['name' => 'Cabang Utama']);
        $branchB = $this->createBranch(['name' => 'Cabang Pembantu']);
        $employee = $this->createUser(['branch_id' => $branchA->id]);

        // Kirim request single shift dengan branch_id B yang tidak sengaja berbeda
        $response = $this->post('/shifts', [
            'mode' => 'single',
            'user_id' => $employee->id,
            'branch_id' => $branchB->id,
            'date' => '2026-10-25',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Pastikan shift tersimpan dengan branch_id Cabang Utama (Branch A)
        $this->assertDatabaseHas('shifts', [
            'user_id' => $employee->id,
            'branch_id' => $branchA->id,
            'status' => 'draft',
        ]);
    }

    public function test_single_shift_auto_populates_branch_if_branch_id_omitted(): void
    {
        $this->actingAsAdmin();

        $branchA = $this->createBranch(['name' => 'Cabang Alfa']);
        $employee = $this->createUser(['branch_id' => $branchA->id]);

        $response = $this->post('/shifts', [
            'mode' => 'single',
            'user_id' => $employee->id,
            'date' => '2026-10-26',
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shifts', [
            'user_id' => $employee->id,
            'branch_id' => $branchA->id,
        ]);
    }

    public function test_shifts_create_view_renders_data_branch_id_and_sync_badge(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch(['name' => 'Cabang Beta']);
        $employee = $this->createUser(['name' => 'Staf Khusus', 'branch_id' => $branch->id]);

        $response = $this->get('/shifts/create');

        $response->assertOk();
        $response->assertSee('id="badge-branch-sync-note"', false);
        $response->assertSee('data-branch-id="' . $branch->id . '"', false);
        $response->assertSee('Staf Khusus');
    }

    public function test_manager_cannot_create_shift_for_employee_of_another_branch(): void
    {
        $branch1 = $this->createBranch(['name' => 'Cabang Manager']);
        $branch2 = $this->createBranch(['name' => 'Cabang Lain']);

        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch1->id]);
        $employeeOtherBranch = $this->createUser(['branch_id' => $branch2->id]);

        $this->actingAs($manager);

        $response = $this->post('/shifts', [
            'mode' => 'single',
            'user_id' => $employeeOtherBranch->id,
            'date' => '2026-10-25',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertForbidden();
    }
}

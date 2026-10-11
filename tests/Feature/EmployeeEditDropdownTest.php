<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeEditDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_edit_page_renders_branch_and_position_as_selected(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch(['name' => 'Cabang Surabaya']);
        $position = $this->createPosition(['name' => 'Senior Developer']);
        $employee = $this->createUser([
            'name' => 'Budi Santoso',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
        ]);

        $response = $this->get("/people/{$employee->id}/edit");

        $response->assertOk();
        $response->assertSee('value="' . $branch->id . '"', false);
        $response->assertSee('value="' . $position->id . '"', false);
        $response->assertSee('Cabang Surabaya');
        $response->assertSee('Senior Developer');
    }

    public function test_people_index_preserves_branch_filter_value(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch(['name' => 'Cabang Jakarta']);

        $response = $this->get("/people?branch_id={$branch->id}");

        $response->assertOk();
        $response->assertSee('name="branch_id"', false);
        $response->assertSee('value="' . $branch->id . '"', false);
        $response->assertSee('Cabang Jakarta');
    }
}

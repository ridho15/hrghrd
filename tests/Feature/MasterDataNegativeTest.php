<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MasterDataNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_creation_fails_with_duplicate_code(): void
    {
        $this->actingAsAdmin();
        $this->createBranch(['code' => 'JKT01']);

        $response = $this->post('/branches', [
            'code' => 'JKT01',
            'name' => 'Cabang Duplikat',
            'latitude' => -6.175,
            'longitude' => 106.827,
            'radius_m' => 100,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_branch_creation_fails_with_invalid_radius_or_coordinates(): void
    {
        $this->actingAsAdmin();

        // Negative radius
        $this->post('/branches', [
            'code' => 'BADRAD',
            'name' => 'Bad Radius',
            'latitude' => -6.175,
            'longitude' => 106.827,
            'radius_m' => -50,
        ])->assertSessionHasErrors('radius_m');

        // Latitude out of world bounds (> 90)
        $this->post('/branches', [
            'code' => 'BADLAT',
            'name' => 'Bad Latitude',
            'latitude' => 95.0,
            'longitude' => 106.827,
            'radius_m' => 100,
        ])->assertSessionHasErrors('latitude');
    }

    public function test_employee_creation_fails_with_duplicate_email(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $pos = $this->createPosition();
        $this->createUser(['email' => 'existing@example.test']);

        $response = $this->post('/people', [
            'name' => 'Duplicate Employee',
            'email' => 'existing@example.test',
            'password' => 'Pass12345!',
            'role' => 'employee',
            'branch_id' => $branch->id,
            'position_id' => $pos->id,
            'hired_at' => '2026-01-01',
            'base_salary' => 5000000,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_employee_creation_fails_when_ended_at_before_hired_at(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $pos = $this->createPosition();

        $response = $this->post('/people', [
            'name' => 'Invalid Date Sequence',
            'email' => 'invalid_dates@example.test',
            'password' => 'Pass12345!',
            'role' => 'employee',
            'branch_id' => $branch->id,
            'position_id' => $pos->id,
            'hired_at' => '2026-10-01',
            'ended_at' => '2026-09-01', // Before hired_at!
            'base_salary' => 5000000,
        ]);

        $response->assertSessionHasErrors('ended_at');
    }

    public function test_employee_creation_fails_with_non_existent_branch(): void
    {
        $this->actingAsAdmin();
        $pos = $this->createPosition();

        $response = $this->post('/people', [
            'name' => 'Fake Branch Employee',
            'email' => 'fake_branch@example.test',
            'password' => 'Pass12345!',
            'role' => 'employee',
            'branch_id' => 999999, // Non-existent
            'position_id' => $pos->id,
            'hired_at' => '2026-01-01',
            'base_salary' => 5000000,
        ]);

        $response->assertSessionHasErrors('branch_id');
    }

    public function test_import_preview_fails_with_executable_or_non_csv_file(): void
    {
        $this->actingAsAdmin();

        $fakeScript = UploadedFile::fake()->create('malicious.sh', 10, 'application/x-sh');

        $response = $this->post('/import/preview', [
            'file' => $fakeScript,
        ]);

        $response->assertSessionHasErrors('file');
    }
}

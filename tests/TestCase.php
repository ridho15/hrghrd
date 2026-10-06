<?php

namespace Tests;

use App\Models\Branch;
use App\Models\Position;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    protected function createBranch(array $attributes = []): Branch
    {
        return Branch::create(array_merge([
            'code' => 'BR_' . uniqid(),
            'name' => 'Branch Test',
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'radius_m' => 100,
            'qr_secret' => 'test-secret',
            'active' => true,
        ], $attributes));
    }

    protected function createPosition(array $attributes = []): Position
    {
        return Position::create(array_merge([
            'name' => 'Pos_' . uniqid(),
        ], $attributes));
    }

    protected function createUser(array $attributes = []): User
    {
        if (array_key_exists('branch_id', $attributes)) {
            $branchId = $attributes['branch_id'];
        } else {
            $branchId = ($attributes['role'] ?? 'employee') !== 'admin' ? $this->createBranch()->id : null;
        }

        $positionId = array_key_exists('position_id', $attributes)
            ? $attributes['position_id']
            : $this->createPosition()->id;

        return User::create(array_merge([
            'name' => 'User ' . uniqid(),
            'email' => 'user_' . uniqid() . '@example.test',
            'password' => Hash::make('Password123!'),
            'role' => 'employee',
            'branch_id' => $branchId,
            'position_id' => $positionId,
            'hired_at' => '2026-01-01',
            'ended_at' => null,
            'base_salary' => 5000000,
            'active' => true,
        ], $attributes));
    }

    protected function actingAsAdmin(): User
    {
        $admin = $this->createUser(['role' => 'admin', 'branch_id' => null]);
        $this->actingAs($admin);

        return $admin;
    }

    protected function actingAsManager(?Branch $branch = null): User
    {
        $branch ??= $this->createBranch();
        $manager = $this->createUser(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager);

        return $manager;
    }

    protected function actingAsEmployee(?Branch $branch = null): User
    {
        $branch ??= $this->createBranch();
        $employee = $this->createUser(['role' => 'employee', 'branch_id' => $branch->id]);
        $this->actingAs($employee);

        return $employee;
    }

    protected function createShift(User $user, Branch $branch, array $attributes = []): Shift
    {
        return Shift::create(array_merge([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'start_at' => '2026-09-15 09:00:00',
            'end_at' => '2026-09-15 17:00:00',
            'status' => 'approved',
            'version' => 1,
        ], $attributes));
    }
}

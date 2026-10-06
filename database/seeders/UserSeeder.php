<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $demoBranch = Branch::where('code', 'DEMO')->first();
        $sbyBranch = Branch::where('code', 'SBY01')->first();
        $stafPos = Position::where('name', 'Staf')->first();
        $mgrPos = Position::where('name', 'Manager Cabang')->first();
        $adminPos = Position::where('name', 'Super Admin')->first();

        $defaultPassword = Hash::make('Demo12345!');

        // 1. Akun Inti Demo
        User::firstOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'Admin Demo',
                'password' => $defaultPassword,
                'role' => 'admin',
                'branch_id' => null,
                'position_id' => $adminPos?->id,
                'hired_at' => now()->subYears(2)->toDateString(),
                'base_salary' => 12000000,
                'active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'manager@example.test'],
            [
                'name' => 'Manager Demo Jakarta',
                'password' => $defaultPassword,
                'role' => 'manager',
                'branch_id' => $demoBranch?->id,
                'position_id' => $mgrPos?->id,
                'hired_at' => now()->subYear()->toDateString(),
                'base_salary' => 7500000,
                'active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'karyawan@example.test'],
            [
                'name' => 'Karyawan Demo',
                'password' => $defaultPassword,
                'role' => 'employee',
                'branch_id' => $demoBranch?->id,
                'position_id' => $stafPos?->id,
                'hired_at' => now()->subMonths(6)->toDateString(),
                'base_salary' => 3000000,
                'active' => true,
            ]
        );

        // 2. Karyawan Tambahan untuk Pengujian Multi-Cabang
        User::firstOrCreate(
            ['email' => 'budi.sby@example.test'],
            [
                'name' => 'Budi Santoso (Surabaya)',
                'password' => $defaultPassword,
                'role' => 'employee',
                'branch_id' => $sbyBranch?->id,
                'position_id' => $stafPos?->id,
                'hired_at' => now()->subMonths(3)->toDateString(),
                'base_salary' => 3200000,
                'active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'siti.jakarta@example.test'],
            [
                'name' => 'Siti Rahma (Jakarta)',
                'password' => $defaultPassword,
                'role' => 'employee',
                'branch_id' => $demoBranch?->id,
                'position_id' => $stafPos?->id,
                'hired_at' => now()->subMonths(8)->toDateString(),
                'base_salary' => 3500000,
                'active' => true,
            ]
        );
    }
}

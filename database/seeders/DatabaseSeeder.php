<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            PositionSeeder::class,
            BranchSeeder::class,
            UserSeeder::class,
            ShiftSeeder::class,
        ]);
    }
}

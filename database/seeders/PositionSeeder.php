<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            'Super Admin',
            'Manager Cabang',
            'Supervisor Operasional',
            'Staf',
            'Kasir',
            'Barista',
            'Teknisi Lapangan',
        ];

        foreach ($positions as $name) {
            Position::firstOrCreate(['name' => $name]);
        }
    }
}

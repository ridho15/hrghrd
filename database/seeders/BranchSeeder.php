<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'code' => 'DEMO',
                'name' => 'Cabang Demo Jakarta (Monas)',
                'latitude' => -6.1753920,
                'longitude' => 106.8271530,
                'radius_m' => 100,
                'qr_secret' => Str::random(64),
                'active' => true,
            ],
            [
                'code' => 'SBY01',
                'name' => 'Cabang Surabaya (Gubeng)',
                'latitude' => -7.2654000,
                'longitude' => 112.7519000,
                'radius_m' => 150,
                'qr_secret' => Str::random(64),
                'active' => true,
            ],
            [
                'code' => 'BDG01',
                'name' => 'Cabang Bandung (Dago)',
                'latitude' => -6.8851000,
                'longitude' => 107.6136000,
                'radius_m' => 120,
                'qr_secret' => Str::random(64),
                'active' => true,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::firstOrCreate(['code' => $branch['code']], $branch);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        $employee = User::where('email', 'karyawan@example.test')->first();
        $siti = User::where('email', 'siti.jakarta@example.test')->first();

        if (!$employee || !$employee->branch_id) {
            return;
        }

        $now = now('Asia/Jakarta');

        // 1. Shift Aktif Hari Ini (Tersedia untuk Check-in)
        Shift::firstOrCreate(
            [
                'user_id' => $employee->id,
                'start_at' => $now->copy()->addMinutes(10)->toDateTimeString(),
            ],
            [
                'branch_id' => $employee->branch_id,
                'end_at' => $now->copy()->addHours(8)->toDateTimeString(),
                'status' => 'approved',
                'version' => 1,
                'approved_by' => $admin?->id,
                'approved_at' => $now,
            ]
        );

        // 2. Shift Besok (Shift Regular 09:00 - 17:00)
        $tomorrow = $now->copy()->addDay()->setTime(9, 0, 0);
        Shift::firstOrCreate(
            [
                'user_id' => $employee->id,
                'start_at' => $tomorrow->toDateTimeString(),
            ],
            [
                'branch_id' => $employee->branch_id,
                'end_at' => $tomorrow->copy()->setTime(17, 0, 0)->toDateTimeString(),
                'status' => 'approved',
                'version' => 1,
                'approved_by' => $admin?->id,
                'approved_at' => $now,
            ]
        );

        // 3. Shift Lintas Tengah Malam (Malam ini 22:00 s/d Besok 06:00)
        if ($siti && $siti->branch_id) {
            $nightStart = $now->copy()->setTime(22, 0, 0);
            $nightEnd = $nightStart->copy()->addDay()->setTime(6, 0, 0);

            Shift::firstOrCreate(
                [
                    'user_id' => $siti->id,
                    'start_at' => $nightStart->toDateTimeString(),
                ],
                [
                    'branch_id' => $siti->branch_id,
                    'end_at' => $nightEnd->toDateTimeString(),
                    'status' => 'approved',
                    'version' => 1,
                    'approved_by' => $admin?->id,
                    'approved_at' => $now,
                ]
            );
        }
    }
}

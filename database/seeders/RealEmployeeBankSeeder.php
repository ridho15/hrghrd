<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RealEmployeeBankSeeder extends Seeder
{
    public function run(): void
    {
        $defaultBranch = Branch::first();
        $defaultPosition = Position::where('name', 'Staf')->first() ?? Position::first();
        $defaultPassword = Hash::make('Demo12345!');

        // Update core demo accounts with clean usernames
        User::where('email', 'admin@example.test')->update(['username' => 'admin']);
        User::where('email', 'manager@example.test')->update(['username' => 'manager']);
        User::where('email', 'karyawan@example.test')->update(['username' => 'karyawan']);

        $realEmployees = [
            [
                'name' => 'Sri Wahyuni Lestari',
                'bank_name' => 'BCA',
                'bank_account_number' => '0680117845',
                'bank_account_name' => 'Sri Wahyuni Lestari',
            ],
            [
                'name' => 'Maria Tupa Welan',
                'bank_name' => 'BCA',
                'bank_account_number' => '1680283199',
                'bank_account_name' => 'Maria Tupa Welan',
            ],
            [
                'name' => 'Tian Anrikana',
                'bank_name' => 'BCA',
                'bank_account_number' => '0020353121',
                'bank_account_name' => 'Tian Anrikana',
            ],
            [
                'name' => 'Vanessa Egga Sherina',
                'bank_name' => 'BCA',
                'bank_account_number' => '2120396806',
                'bank_account_name' => 'Vanessa Egga Sherina',
            ],
            [
                'name' => 'Fikri Apriadi',
                'bank_name' => 'BCA',
                'bank_account_number' => '0382498087',
                'bank_account_name' => 'Fikri Apriadi',
            ],
            [
                'name' => 'Puspita Rahmawati',
                'bank_name' => 'BCA',
                'bank_account_number' => '5445502054',
                'bank_account_name' => 'Puspita Rahmawati',
            ],
            [
                'name' => 'Rani dwi Pratiwi',
                'bank_name' => 'BCA',
                'bank_account_number' => '6170762833',
                'bank_account_name' => 'Rani dwi Pratiwi',
            ],
            [
                'name' => 'Alfitriani',
                'bank_name' => 'BCA',
                'bank_account_number' => '6170620532',
                'bank_account_name' => 'Alfitriani',
            ],
            [
                'name' => 'Ockta Laila rahma',
                'bank_name' => 'BCA',
                'bank_account_number' => '7573054280',
                'bank_account_name' => 'Ockta Laila rahma',
            ],
            [
                'name' => 'Rifqa Deba Ainaika',
                'bank_name' => 'BCA',
                'bank_account_number' => '7015781874',
                'bank_account_name' => 'Rifqa Deba Ainaika',
            ],
            [
                'name' => 'Taryuni',
                'bank_name' => 'BCA',
                'bank_account_number' => '2120387548',
                'bank_account_name' => 'Taryuni',
            ],
            [
                'name' => 'Fidelia Sukarno Prameswari',
                'bank_name' => 'BCA',
                'bank_account_number' => '5850349187',
                'bank_account_name' => 'Fidelia Sukarno Prameswari',
            ],
            [
                'name' => 'Arin Sadita',
                'bank_name' => 'BCA',
                'bank_account_number' => '2880710683',
                'bank_account_name' => 'Arin Sadita',
            ],
            [
                'name' => 'Muhamad Ramdhan',
                'bank_name' => 'BCA',
                'bank_account_number' => '6500318030',
                'bank_account_name' => 'Muhamad Ramdhan',
            ],
            [
                'name' => 'Karyawan Tunai',
                'bank_name' => 'CASH',
                'bank_account_number' => '-',
                'bank_account_name' => 'Pembayaran Tunai',
            ],
            [
                'name' => 'Novita Rahmawati',
                'bank_name' => 'BCA',
                'bank_account_number' => '7570834660',
                'bank_account_name' => 'Novita Rahmawati',
            ],
            [
                'name' => 'Devon Chastino Cuaca',
                'bank_name' => 'BCA',
                'bank_account_number' => '5775669851',
                'bank_account_name' => 'Devon Chastino Cuaca',
            ],
            [
                'name' => 'Ahmad Zaki Yamani',
                'bank_name' => 'BCA',
                'bank_account_number' => '5540864928',
                'bank_account_name' => 'Ahmad Zaki Yamani',
            ],
            [
                'name' => 'Putri Lulu Aprilianty',
                'bank_name' => 'BCA',
                'bank_account_number' => '5491080681',
                'bank_account_name' => 'Putri Lulu Aprilianty',
            ],
            [
                'name' => 'Nabitalia Putri Rosnawati',
                'bank_name' => 'BCA',
                'bank_account_number' => '5370382702',
                'bank_account_name' => 'Nabitalia Putri Rosnawati',
            ],
            [
                'name' => 'Puput Trianingsih',
                'bank_name' => 'BCA',
                'bank_account_number' => '971697066',
                'bank_account_name' => 'Puput Trianingsih',
            ],
            [
                'name' => 'Siti Rahmawati',
                'bank_name' => 'BCA',
                'bank_account_number' => '5931179846',
                'bank_account_name' => 'Siti Rahmawati',
            ],
            [
                'name' => 'Mohammad Erik',
                'bank_name' => 'BCA',
                'bank_account_number' => '6500435141',
                'bank_account_name' => 'Mohammad Erik',
            ],
            [
                'name' => 'Wilem Serin',
                'bank_name' => 'BCA',
                'bank_account_number' => '2680206123',
                'bank_account_name' => 'Wilem Serin',
            ],
            [
                'name' => 'Aura Jasmina Dewi',
                'bank_name' => 'BCA',
                'bank_account_number' => '0700432653',
                'bank_account_name' => 'Aura Jasmina Dewi',
            ],
            [
                'name' => 'Putri Salsabila',
                'bank_name' => 'BCA',
                'bank_account_number' => '2480720809',
                'bank_account_name' => 'Putri Salsabila',
            ],
            [
                'name' => 'Siti Asmiyanti',
                'bank_name' => 'BCA',
                'bank_account_number' => '1280874376',
                'bank_account_name' => 'Siti Asmiyanti',
            ],
        ];

        foreach ($realEmployees as $emp) {
            $slugParts = explode(' ', Str::lower(trim($emp['name'])));
            $username = count($slugParts) > 1 ? $slugParts[0] . '.' . $slugParts[1] : $slugParts[0];
            $cleanUsername = preg_replace('/[^a-z0-9\.]/', '', $username);
            $email = Str::slug($emp['name'], '.') . '@hrgroup.local';

            User::updateOrCreate(
                ['name' => $emp['name']],
                [
                    'username' => $cleanUsername,
                    'email' => $email,
                    'password' => $defaultPassword,
                    'role' => 'employee',
                    'branch_id' => $defaultBranch?->id,
                    'position_id' => $defaultPosition?->id,
                    'hired_at' => now()->subMonths(6)->toDateString(),
                    'base_salary' => 3000000,
                    'bank_name' => $emp['bank_name'],
                    'bank_account_number' => $emp['bank_account_number'],
                    'bank_account_name' => $emp['bank_account_name'],
                    'annual_leave_quota' => 12,
                    'active' => true,
                ]
            );
        }
    }
}

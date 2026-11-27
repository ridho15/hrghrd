<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Kebijakan HRG: periode payroll 21 s.d. 20 bulan berikutnya, lembur Rp10.000 per jam penuh.
     *
     * @var array<string, string>
     */
    private array $policy = [
        'payroll_cutoff_day' => '20',
        'overtime_rate_scheme' => 'fixed_amount',
        'overtime_unit_minutes' => '60',
        'overtime_rate_per_unit' => '10000',
        'overtime_rounding_mode' => 'floor',
    ];

    /**
     * Nilai sebelum kebijakan ini, dipakai saat rollback.
     *
     * @var array<string, string>
     */
    private array $previous = [
        'overtime_rate_scheme' => 'statutory_pp35',
        'overtime_unit_minutes' => '60',
        'overtime_rate_per_unit' => '20000',
        'overtime_rounding_mode' => 'floor',
    ];

    public function up(): void
    {
        foreach ($this->policy as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'payroll_cutoff_day')->update(['value' => '0', 'updated_at' => now()]);
        foreach ($this->previous as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        }
    }
};

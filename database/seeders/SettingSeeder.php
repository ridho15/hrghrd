<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Rules;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Rules::DEFAULTS as $key => $defaultValue) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => (string) $defaultValue]
            );
        }
    }
}

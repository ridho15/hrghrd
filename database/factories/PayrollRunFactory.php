<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'month' => now()->subMonth()->format('Y-m'),
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
            'locked_by' => null,
            'locked_at' => null,
        ];
    }

    public function approved(?User $admin = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => $admin?->id ?? User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    public function locked(?User $admin = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'locked',
            'approved_by' => $admin?->id ?? User::factory()->admin(),
            'approved_at' => now()->subDays(2),
            'locked_by' => $admin?->id ?? User::factory()->admin(),
            'locked_at' => now(),
        ]);
    }
}

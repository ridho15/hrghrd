<?php

namespace Database\Factories;

use App\Models\PayrollAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollAdjustment>
 */
class PayrollAdjustmentFactory extends Factory
{
    protected $model = PayrollAdjustment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'month' => now()->format('Y-m'),
            'amount' => fake()->randomElement([50000, 100000, 250000, -50000, -100000]),
            'reason' => fake()->sentence(5),
            'created_by' => User::factory()->admin(),
        ];
    }
}

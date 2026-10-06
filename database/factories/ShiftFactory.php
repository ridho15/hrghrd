<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        $start = Carbon::parse(fake()->dateTimeBetween('now', '+7 days'))->setTime(9, 0, 0);
        $end = $start->copy()->setTime(17, 0, 0);

        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'start_at' => $start,
            'end_at' => $end,
            'status' => 'draft',
            'version' => 1,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function approved(?User $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => $approver?->id ?? User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }
}

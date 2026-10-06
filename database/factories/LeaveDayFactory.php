<?php

namespace Database\Factories;

use App\Models\LeaveDay;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveDay>
 */
class LeaveDayFactory extends Factory
{
    protected $model = LeaveDay::class;

    public function definition(): array
    {
        return [
            'leave_request_id' => LeaveRequest::factory(),
            'date' => fake()->date(),
            'status' => 'pending',
            'paid' => false,
        ];
    }

    public function approved(bool $paid = false): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'paid' => $paid,
        ]);
    }
}

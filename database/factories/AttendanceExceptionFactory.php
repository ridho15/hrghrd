<?php

namespace Database\Factories;

use App\Models\AttendanceException;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceException>
 */
class AttendanceExceptionFactory extends Factory
{
    protected $model = AttendanceException::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shift_id' => Shift::factory(),
            'action' => fake()->randomElement(['in', 'out']),
            'reason' => 'Kendala GPS ponsel tidak merespons di lokasi.',
            'status' => 'pending',
            'reviewed_by' => null,
            'review_note' => null,
            'reviewed_at' => null,
        ];
    }

    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_by' => $reviewer?->id ?? User::factory()->manager(),
            'review_note' => 'Disetujui setelah diverifikasi.',
            'reviewed_at' => now(),
        ]);
    }
}

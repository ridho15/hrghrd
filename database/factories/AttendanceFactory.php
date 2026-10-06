<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory()->approved(),
            'user_id' => fn (array $attributes) => Shift::find($attributes['shift_id'])?->user_id ?? User::factory(),
            'checkin_at' => now()->setTime(8, 55, 0),
            'checkout_at' => now()->setTime(17, 5, 0),
            'status' => 'present',
            'late_minutes' => 0,
            'late_units' => 0,
            'overtime_minutes' => 0,
            'overtime_approved_by' => null,
            'checkin_evidence' => [
                'latitude' => -6.17539,
                'longitude' => 106.82715,
                'accuracy_m' => 15,
                'distance_m' => 20,
            ],
            'checkout_evidence' => [
                'latitude' => -6.17539,
                'longitude' => 106.82715,
                'accuracy_m' => 15,
                'distance_m' => 20,
            ],
            'flags' => null,
        ];
    }

    public function late(int $minutes = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'late',
            'late_minutes' => $minutes,
            'late_units' => (int) ceil(($minutes - 15) / 15),
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'absent',
            'checkin_at' => null,
            'checkout_at' => null,
            'late_minutes' => 0,
            'late_units' => 0,
        ]);
    }
}

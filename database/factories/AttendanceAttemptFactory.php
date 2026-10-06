<?php

namespace Database\Factories;

use App\Models\AttendanceAttempt;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceAttempt>
 */
class AttendanceAttemptFactory extends Factory
{
    protected $model = AttendanceAttempt::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shift_id' => Shift::factory(),
            'action' => fake()->randomElement(['in', 'out']),
            'result' => 'accepted',
            'reason' => null,
            'evidence' => [
                'latitude' => -6.17539,
                'longitude' => 106.82715,
                'accuracy_m' => 10,
                'distance_m' => 15,
                'qr_valid' => true,
                'device_matches' => true,
                'ip' => fake()->ipv4(),
            ],
            'server_at' => now(),
        ];
    }

    public function rejected(string $reason = 'Lokasi di luar area'): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => 'rejected',
            'reason' => $reason,
        ]);
    }
}

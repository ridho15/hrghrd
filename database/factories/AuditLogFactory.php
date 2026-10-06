<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'subject_type' => 'user',
            'subject_id' => fn (array $attributes) => $attributes['actor_id'],
            'action' => 'update',
            'before' => ['status' => 'draft'],
            'after' => ['status' => 'approved'],
            'reason' => fake()->sentence(),
            'ip' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}

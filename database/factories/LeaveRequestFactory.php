<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $start = Carbon::parse(fake()->dateTimeBetween('+8 days', '+20 days'));
        $end = $start->copy()->addDays(fake()->numberBetween(0, 2));

        return [
            'user_id' => User::factory(),
            'created_by' => fn (array $attributes) => $attributes['user_id'],
            'type' => 'leave',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'reason' => fake()->sentence(8),
            'certificate_path' => null,
            'certificate_name' => null,
            'status' => 'pending',
            'reviewed_by' => null,
            'review_note' => null,
            'reviewed_at' => null,
        ];
    }

    public function sick(): static
    {
        $start = Carbon::parse(fake()->dateTimeBetween('-5 days', 'now'));
        $end = $start->copy()->addDays(fake()->numberBetween(1, 2));

        return $this->state(fn (array $attributes) => [
            'type' => 'sick',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'certificate_path' => 'certificates/sample.pdf',
            'certificate_name' => 'surat-dokter.pdf',
        ]);
    }

    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_by' => $reviewer?->id ?? User::factory()->manager(),
            'review_note' => 'Disetujui',
            'reviewed_at' => now(),
        ]);
    }
}

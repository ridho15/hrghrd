<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Attendance
 */
class AttendanceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'shift_id'         => $this->shift_id,
            'status'           => $this->status,
            'checkin_at'       => $this->checkin_at?->toIso8601String(),
            'checkout_at'      => $this->checkout_at?->toIso8601String(),
            'late_minutes'     => $this->late_minutes,
            'late_units'       => $this->late_units,
            'overtime_minutes' => $this->overtime_minutes,
            'flags'            => $this->flags ?? [],
            'shift'            => $this->whenLoaded('shift', fn () => [
                'id'       => $this->shift->id,
                'start_at' => $this->shift->start_at?->toIso8601String(),
                'end_at'   => $this->shift->end_at?->toIso8601String(),
                'branch'   => $this->shift->branch ? [
                    'id'   => $this->shift->branch->id,
                    'name' => $this->shift->branch->name,
                ] : null,
            ]),
            'user'             => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at'       => $this->created_at->toIso8601String(),
        ];
    }
}

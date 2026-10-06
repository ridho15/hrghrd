<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shift
 */
class ShiftResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'start_at'        => $this->start_at->toIso8601String(),
            'end_at'          => $this->end_at->toIso8601String(),
            'status'          => $this->status,
            'version'         => $this->version,
            'approved_at'     => $this->approved_at?->toIso8601String(),
            'user'            => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'branch'          => $this->whenLoaded('branch', fn () => [
                'id'   => $this->branch->id,
                'name' => $this->branch->name,
                'code' => $this->branch->code,
            ]),
            'attendance'      => $this->whenLoaded('attendance', fn () => $this->attendance ? [
                'id'              => $this->attendance->id,
                'status'          => $this->attendance->status,
                'checkin_at'      => $this->attendance->checkin_at?->toIso8601String(),
                'checkout_at'     => $this->attendance->checkout_at?->toIso8601String(),
                'late_minutes'    => $this->attendance->late_minutes,
                'overtime_minutes'=> $this->attendance->overtime_minutes,
            ] : null),
        ];
    }
}

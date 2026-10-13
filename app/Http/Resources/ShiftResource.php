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
            'is_open_slot'    => $this->user_id === null,
            'start_at'        => $this->start_at->toIso8601String(),
            'end_at'          => $this->end_at->toIso8601String(),
            'status'          => $this->status,
            'version'         => $this->version,
            'approved_at'     => $this->approved_at?->toIso8601String(),
            'user'            => $this->whenLoaded('user', fn () => $this->user ? [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'position'        => $this->whenLoaded('position', fn () => $this->position ? [
                'id'   => $this->position->id,
                'name' => $this->position->name,
            ] : null),
            'position_name'   => $this->position_name,
            'branch'          => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id'   => $this->branch->id,
                'name' => $this->branch->name,
                'code' => $this->branch->code,
            ] : null),
            'attendance'      => $this->whenLoaded('attendance', function () {
                if (! $this->attendance) {
                    return null;
                }
                $isLate = (int) ($this->attendance->late_minutes ?? 0) > 0;
                $hasOvertime = (int) ($this->attendance->overtime_minutes ?? 0) > 0;

                return [
                    'id'              => $this->attendance->id,
                    'status'          => $this->attendance->status,
                    'checkin_at'      => $this->attendance->checkin_at?->toIso8601String(),
                    'checkout_at'     => $this->attendance->checkout_at?->toIso8601String(),
                    'late_minutes'    => $this->attendance->late_minutes,
                    'is_late'         => $isLate,
                    'late_display'    => $isLate ? "{$this->attendance->late_minutes} Menit" : null,
                    'late_badge_label'=> $isLate ? "Terlambat {$this->attendance->late_minutes}m" : ($this->attendance->checkin_at ? 'Tepat Waktu' : null),
                    'overtime_minutes'=> $this->attendance->overtime_minutes,
                    'has_overtime'    => $hasOvertime,
                    'overtime_display'=> $hasOvertime ? "{$this->attendance->overtime_minutes} Menit" : null,
                ];
            }),
        ];
    }
}

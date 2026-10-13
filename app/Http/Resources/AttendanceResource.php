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
        $latestException = $this->shift?->exceptions?->sortByDesc('id')->first() ?? $this->exception;

        $isLate = (int) ($this->late_minutes ?? 0) > 0;
        $hasOvertime = (int) ($this->overtime_minutes ?? 0) > 0;

        return [
            'id'               => $this->id,
            'shift_id'         => $this->shift_id,
            'status'           => $this->status,
            'checkin_at'       => $this->checkin_at?->toIso8601String(),
            'checkout_at'      => $this->checkout_at?->toIso8601String(),
            'late_minutes'     => $this->late_minutes,
            'late_units'       => $this->late_units,
            'is_late'          => $isLate,
            'late_display'     => $isLate ? "{$this->late_minutes} Menit" : null,
            'late_badge_label' => $isLate ? "Terlambat {$this->late_minutes}m" : ($this->checkin_at ? 'Tepat Waktu' : null),
            'overtime_minutes' => $this->overtime_minutes,
            'has_overtime'     => $hasOvertime,
            'overtime_display' => $hasOvertime ? "{$this->overtime_minutes} Menit" : null,
            // Tambahan field baru untuk sinkronisasi Mobile:
            'exception_status' => $latestException?->status,
            'review_note'      => $latestException?->review_note,
            'is_rejected'      => $this->status === 'rejected' || $this->status === 'absent' || $latestException?->status === 'rejected',
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

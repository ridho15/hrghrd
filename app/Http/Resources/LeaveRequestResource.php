<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'type'              => $this->type,
            'type_label'        => $this->type_label,
            'original_leave_id' => $this->original_leave_id,
            'vacate_shifts'     => (bool) $this->vacate_shifts,
            'status'            => $this->status,
            'reason'            => $this->reason,
            'start_date'        => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date'          => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'has_certificate'   => (bool) $this->certificate_path,
            'review_note'       => $this->review_note,
            'user'             => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'branch' => $this->user->branch ? [
                    'id'   => $this->user->branch->id,
                    'name' => $this->user->branch->name,
                ] : null,
            ]),
            'days'             => $this->whenLoaded('days', fn () => $this->days->map(fn ($d) => [
                'id'     => $d->id,
                'date'   => $d->date,
                'status' => $d->status,
                'paid'   => $d->paid,
            ])->values()),
            'created_at'       => $this->created_at->toIso8601String(),
            'updated_at'       => $this->updated_at->toIso8601String(),
        ];
    }
}

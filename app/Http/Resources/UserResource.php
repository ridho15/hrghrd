<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'role'          => $this->role,
            'active'        => $this->active,
            'hired_at'      => $this->hired_at?->toDateString(),
            'base_salary'   => $this->base_salary,
            'branch'        => $this->whenLoaded('branch', fn () => [
                'id'   => $this->branch->id,
                'name' => $this->branch->name,
                'code' => $this->branch->code,
            ]),
            'position'      => $this->whenLoaded('position', fn () => [
                'id'   => $this->position->id,
                'name' => $this->position->name,
            ]),
            'device_hash'   => $this->device_hash,
            'created_at'    => $this->created_at->toIso8601String(),
        ];
    }
}

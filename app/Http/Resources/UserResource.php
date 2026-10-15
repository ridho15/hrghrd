<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Hash;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'username'             => $this->username,
            'email'                => $this->email,
            'role'                 => $this->role,
            'schedule_type'        => $this->schedule_type ?? 'shift',
            'active'               => $this->active,
            'must_change_password' => (bool) ($this->must_change_password || ($this->password ? Hash::check('12345678', $this->password) : false)),
            'hired_at'             => $this->hired_at?->toDateString(),
            'base_salary'          => $this->base_salary,
            'branch'               => $this->whenLoaded('branch', fn () => [
                'id'   => $this->branch->id,
                'name' => $this->branch->name,
                'code' => $this->branch->code,
            ]),
            'position'             => $this->whenLoaded('position', fn () => [
                'id'   => $this->position->id,
                'name' => $this->position->name,
            ]),
            'device_hash'          => $this->device_hash,
            'created_at'           => $this->created_at->toIso8601String(),
        ];
    }
}

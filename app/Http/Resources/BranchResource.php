<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Branch
 */
class BranchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'code'           => $this->code,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'radius_m'       => $this->radius_m,
            'active'         => (bool) $this->active,
        ];
    }
}

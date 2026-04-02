<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubgroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'full_name'   => $this->full_name,
            'description' => $this->description,
            'chairperson' => $this->chairperson,
            'is_active'   => $this->is_active,
        ];
    }
}

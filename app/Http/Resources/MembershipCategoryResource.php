<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipCategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'designation'  => $this->designation,
            'description'  => $this->description,
            'requirements' => $this->requirements,
            'annual_fee'   => $this->annual_fee,
            'rank'         => $this->rank,
            'is_active'    => $this->is_active,
        ];
    }
}

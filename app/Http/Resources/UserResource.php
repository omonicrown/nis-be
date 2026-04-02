<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'first_name'            => $this->first_name,
            'last_name'             => $this->last_name,
            'other_names'           => $this->other_names,
            'full_name'             => $this->full_name,
            'email'                 => $this->email,
            'phone'                 => $this->phone,
            'gender'                => $this->gender,
            'avatar'                => $this->avatar,
            'surcon_reg_no'         => $this->surcon_reg_no,
            'nis_membership_id'     => $this->nis_membership_id,
            'suffix'                => $this->suffix,
            'designation'           => $this->designation,
            'status'                => $this->status?->value,
            'status_label'          => $this->status?->label(),
            'email_verified_at'     => $this->email_verified_at?->toIso8601String(),
            'profile_completed'     => $this->profile_completed,
            'is_admin'              => $this->isAdmin(),

            // Relations (loaded conditionally)
            'membership_category'   => new MembershipCategoryResource($this->whenLoaded('membershipCategory')),
            'role'                  => $this->whenLoaded('role', fn() => [
                'id'   => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ]),
            'profile'               => new MemberProfileResource($this->whenLoaded('profile')),
            'subgroups'             => SubgroupResource::collection($this->whenLoaded('subgroups')),
            'executive_position'    => $this->whenLoaded('currentExecutivePosition', fn() => [
                'title'          => $this->currentExecutivePosition->title,
                'designation'    => $this->currentExecutivePosition->designation,
                'position_order' => $this->currentExecutivePosition->position_order,
            ]),

            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}

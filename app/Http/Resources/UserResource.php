<?php

namespace App\Http\Resources;

use App\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        // Handle status - might be enum or string depending on cast
        $statusValue = $this->status instanceof UserStatus ? $this->status->value : $this->status;
        $statusLabel = $this->status instanceof UserStatus ? $this->status->label() : ucfirst($this->status ?? '');

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
            'status'                => $statusValue,
            'status_label'          => $statusLabel,
            'email_verified_at'     => $this->formatDate($this->email_verified_at),
            'profile_completed'     => (bool) $this->profile_completed,
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

            'created_at'  => $this->formatDate($this->created_at),
            'updated_at'  => $this->formatDate($this->updated_at),
        ];
    }

    /**
     * Safely format a date field - handles Carbon objects, strings, and nulls.
     */
    private function formatDate($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \Carbon\Carbon || $value instanceof \DateTimeInterface) {
            return $value->toIso8601String();
        }

        // It's a raw string from DB - return as-is or try to parse
        if (is_string($value)) {
            try {
                return \Carbon\Carbon::parse($value)->toIso8601String();
            } catch (\Exception $e) {
                return $value;
            }
        }

        return null;
    }
}

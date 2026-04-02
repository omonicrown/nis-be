<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'status'           => $this->status,
            'check_in_method'  => $this->check_in_method,
            'checked_in_at'    => $this->checked_in_at?->toIso8601String(),
            'note'             => $this->note,
            'member'           => $this->whenLoaded('user', fn() => [
                'id'                  => $this->user->id,
                'full_name'           => $this->user->full_name,
                'nis_membership_id'   => $this->user->nis_membership_id,
                'surcon_reg_no'       => $this->user->surcon_reg_no,
                'membership_category' => $this->user->membershipCategory?->name,
                'designation'         => $this->user->membershipCategory?->designation,
            ]),
            'marked_by' => $this->whenLoaded('markedBy', fn() => [
                'id'        => $this->markedBy->id,
                'full_name' => $this->markedBy->full_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

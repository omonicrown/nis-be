<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                     => $this->id,
            'title'                  => $this->title,
            'slug'                   => $this->slug,
            'description'            => $this->description,
            'details'                => $this->when($request->route('event') !== null, $this->details),
            'banner_url'             => $this->banner_url,
            'start_date'             => $this->start_date?->format('Y-m-d'),
            'end_date'               => $this->end_date?->format('Y-m-d'),
            'start_time'             => $this->start_time,
            'end_time'               => $this->end_time,
            'venue'                  => $this->venue,
            'address'                => $this->address,
            'is_virtual'             => $this->is_virtual,
            'virtual_link'           => $this->when($request->user() !== null, $this->virtual_link),
            'type'                   => $this->type,
            'status'                 => $this->status,
            'requires_registration'  => $this->requires_registration,
            'max_attendees'          => $this->max_attendees,
            'registration_fee'       => $this->registration_fee,
            'registration_deadline'  => $this->registration_deadline?->toIso8601String(),
            'is_registration_open'   => $this->isRegistrationOpen(),
            'is_full'                => $this->isFull(),
            'registered_count'       => $this->registeredCount(),
            'created_by'             => $this->whenLoaded('creator', fn() => [
                'id'        => $this->creator->id,
                'full_name' => $this->creator->full_name,
            ]),
            'my_registration'        => $this->when(
                $request->user() && $this->relationLoaded('registrations'),
                fn() => $this->registrations->where('user_id', $request->user()->id)->first()?->only(['id', 'status'])
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

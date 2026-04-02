<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'body'       => $this->body,
            'priority'   => $this->priority,
            'visibility' => $this->visibility,
            'is_active'  => $this->is_active,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn() => [
                'id'        => $this->creator->id,
                'full_name' => $this->creator->full_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

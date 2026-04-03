<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'meeting_date'    => $this->meeting_date?->format('Y-m-d'),
            'start_time'      => $this->start_time,
            'end_time'        => $this->end_time,
            'venue'           => $this->venue,
            'type'            => $this->type,
            'status'          => $this->status,
            'agenda'          => $this->agenda,
            'has_minutes'     => $this->hasMinutes(),
            'minutes_text'    => $this->when($this->minutes_text, $this->minutes_text),
            'minutes_file_url' => $this->minutes_file_url,
            'qr_code'         => $this->when($request->user()?->isAdmin(), $this->qr_code),
            'qr_code_url'     => $this->qr_code_url,
            'is_upcoming'     => $this->isUpcoming(),
            'created_by'      => $this->whenLoaded('creator', fn() => [
                'id'        => $this->creator->id,
                'full_name' => $this->creator->full_name,
            ]),

            // Attendance summary (loaded conditionally)
            'attendance_summary' => $this->when($this->relationLoaded('attendances'), fn() => [
                'present' => $this->attendances->where('status', 'present')->count(),
                'absent'  => $this->attendances->where('status', 'absent')->count(),
                'apology' => $this->attendances->where('status', 'apology')->count(),
                'total'   => $this->attendances->count(),
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

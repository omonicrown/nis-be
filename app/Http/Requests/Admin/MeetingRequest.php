<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'meeting_date' => ['required', 'date'],
            'start_time'   => ['nullable', 'date_format:H:i'],
            'end_time'     => ['nullable', 'date_format:H:i', 'after:start_time'],
            'venue'        => ['nullable', 'string', 'max:500'],
            'type'         => ['required', 'in:general,special,emergency,executive'],
            'status'       => ['nullable', 'in:scheduled,ongoing,completed,cancelled'],
            'agenda'       => ['nullable', 'string'],
        ];
    }
}

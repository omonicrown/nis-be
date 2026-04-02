<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                  => ['required', 'string', 'max:255'],
            'description'            => ['nullable', 'string', 'max:1000'],
            'details'                => ['nullable', 'string'],
            'start_date'             => ['required', 'date'],
            'end_date'               => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time'             => ['nullable', 'date_format:H:i'],
            'end_time'               => ['nullable', 'date_format:H:i'],
            'venue'                  => ['nullable', 'string', 'max:500'],
            'address'                => ['nullable', 'string', 'max:500'],
            'is_virtual'             => ['nullable', 'boolean'],
            'virtual_link'           => ['nullable', 'url', 'max:500'],
            'type'                   => ['required', 'in:seminar,workshop,conference,agm,social,training,other'],
            'status'                 => ['nullable', 'in:upcoming,ongoing,completed,cancelled'],
            'requires_registration'  => ['nullable', 'boolean'],
            'max_attendees'          => ['nullable', 'integer', 'min:1'],
            'registration_fee'       => ['nullable', 'numeric', 'min:0'],
            'registration_deadline'  => ['nullable', 'date'],
        ];
    }
}

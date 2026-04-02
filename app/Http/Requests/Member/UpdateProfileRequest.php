<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // User fields
            'first_name'               => ['sometimes', 'string', 'max:100'],
            'last_name'                => ['sometimes', 'string', 'max:100'],
            'other_names'              => ['nullable', 'string', 'max:100'],
            'phone'                    => ['nullable', 'string', 'max:20'],
            'gender'                   => ['nullable', 'in:male,female'],

            // Profile fields
            'office_address'           => ['nullable', 'string', 'max:500'],
            'residential_address'      => ['nullable', 'string', 'max:500'],
            'date_of_birth'            => ['nullable', 'date', 'before:today'],
            'bio'                      => ['nullable', 'string', 'max:1000'],
            'specialization'           => ['nullable', 'string', 'max:255'],
            'firm_name'                => ['nullable', 'string', 'max:255'],
            'year_of_registration'     => ['nullable', 'integer', 'min:1900', 'max:' . date('Y')],

            // Privacy settings
            'show_email'               => ['nullable', 'boolean'],
            'show_phone'               => ['nullable', 'boolean'],
            'show_office_address'      => ['nullable', 'boolean'],
            'show_residential_address' => ['nullable', 'boolean'],
            'show_in_directory'        => ['nullable', 'boolean'],

            // Subgroups
            'subgroup_ids'             => ['nullable', 'array'],
            'subgroup_ids.*'           => ['exists:subgroups,id'],
        ];
    }
}

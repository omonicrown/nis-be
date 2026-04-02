<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'             => ['required', 'string', 'max:100'],
            'last_name'              => ['required', 'string', 'max:100'],
            'other_names'            => ['nullable', 'string', 'max:100'],
            'email'                  => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone'                  => ['nullable', 'string', 'max:20'],
            'gender'                 => ['nullable', 'in:male,female'],
            'password'               => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'surcon_reg_no'          => ['nullable', 'string', 'max:50'],
            'nis_membership_id'      => ['nullable', 'string', 'max:50'],
            'membership_category_id' => ['required', 'exists:membership_categories,id'],

            // Profile fields (optional at registration)
            'office_address'         => ['nullable', 'string', 'max:500'],
            'residential_address'    => ['nullable', 'string', 'max:500'],
            'date_of_birth'          => ['nullable', 'date', 'before:today'],
            'specialization'         => ['nullable', 'string', 'max:255'],
            'firm_name'              => ['nullable', 'string', 'max:255'],

            // Subgroups
            'subgroup_ids'           => ['nullable', 'array'],
            'subgroup_ids.*'         => ['exists:subgroups,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'membership_category_id.required' => 'Please select your membership category.',
            'membership_category_id.exists'   => 'The selected membership category is invalid.',
            'email.unique'                     => 'A member with this email address already exists.',
        ];
    }
}

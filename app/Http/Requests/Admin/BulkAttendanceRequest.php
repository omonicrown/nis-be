<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendances'            => ['required', 'array', 'min:1'],
            'attendances.*.user_id'  => ['required', 'exists:users,id'],
            'attendances.*.status'   => ['required', 'in:present,absent,apology'],
            'attendances.*.note'     => ['nullable', 'string', 'max:500'],
        ];
    }
}

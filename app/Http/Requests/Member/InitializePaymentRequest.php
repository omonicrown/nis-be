<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class InitializePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'           => ['required', 'in:membership_dues,event_registration,donation,other'],
            'amount'         => ['required', 'numeric', 'min:100'],
            'description'    => ['nullable', 'string', 'max:500'],
            'payment_year'   => ['nullable', 'integer', 'min:2020', 'max:2050'],
            'payment_period' => ['nullable', 'string', 'max:50'],
            'method'         => ['required', 'in:paystack,bank_transfer,cash'],
            'callback_url'   => ['nullable', 'url'],  // Frontend redirect URL after Paystack

            // Manual payment fields
            'manual_reference' => ['required_if:method,bank_transfer', 'nullable', 'string', 'max:255'],
            'manual_note'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'manual_reference.required_if' => 'Please provide a bank transfer reference number.',
            'amount.min' => 'Minimum payment amount is ₦100.',
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'type'            => $this->type,
            'description'     => $this->description,
            'amount'          => $this->amount,
            'currency'        => $this->currency,
            'method'          => $this->method,
            'status'          => $this->status,
            'receipt_number'  => $this->receipt_number,
            'payment_year'    => $this->payment_year,
            'payment_period'  => $this->payment_period,
            'paid_at'         => $this->paid_at?->toIso8601String(),

            // Paystack (only show to the paying member or admin)
            'paystack_reference'        => $this->paystack_reference,
            'paystack_authorization_url' => $this->when($this->isPending() && $this->method === 'paystack', $this->paystack_authorization_url),
            'paystack_channel'          => $this->paystack_channel,

            // Manual payment details
            'manual_reference'     => $this->manual_reference,
            'manual_proof_url'     => $this->manual_proof_url,
            'manual_note'          => $this->manual_note,

            // Admin verification
            'verified_by' => $this->whenLoaded('verifiedBy', fn() => [
                'id'        => $this->verifiedBy->id,
                'full_name' => $this->verifiedBy->full_name,
            ]),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'admin_note'  => $this->admin_note,

            // Member info (for admin views)
            'user' => $this->whenLoaded('user', fn() => [
                'id'                  => $this->user->id,
                'full_name'           => $this->user->full_name,
                'email'               => $this->user->email,
                'nis_membership_id'   => $this->user->nis_membership_id,
                'membership_category' => $this->user->membershipCategory?->name,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

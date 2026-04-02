<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'office_address'           => $this->office_address,
            'residential_address'      => $this->residential_address,
            'date_of_birth'            => $this->date_of_birth?->format('Y-m-d'),
            'bio'                      => $this->bio,
            'specialization'           => $this->specialization,
            'firm_name'                => $this->firm_name,
            'year_of_registration'     => $this->year_of_registration,
            'show_email'               => $this->show_email,
            'show_phone'               => $this->show_phone,
            'show_office_address'      => $this->show_office_address,
            'show_residential_address' => $this->show_residential_address,
            'show_in_directory'        => $this->show_in_directory,
        ];
    }
}

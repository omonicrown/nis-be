<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberProfile extends Model
{
    protected $fillable = [
        'user_id', 'office_address', 'residential_address',
        'date_of_birth', 'bio', 'specialization', 'firm_name',
        'year_of_registration',
        'show_email', 'show_phone', 'show_office_address',
        'show_residential_address', 'show_in_directory',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'show_email' => 'boolean',
            'show_phone' => 'boolean',
            'show_office_address' => 'boolean',
            'show_residential_address' => 'boolean',
            'show_in_directory' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

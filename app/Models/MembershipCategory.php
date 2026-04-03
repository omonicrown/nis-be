<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'designation', 'description',
        'requirements', 'annual_fee', 'rank', 'is_active',
    ];

    protected $casts = [
            'annual_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];

    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'membership_category_id');
    }

    public function activeMembersCount(): int
    {
        return $this->members()->active()->count();
    }
}

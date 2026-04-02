<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subgroup extends Model
{
    protected $fillable = [
        'name', 'slug', 'full_name', 'description',
        'chairperson', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'member_subgroup')
            ->withTimestamps();
    }
}

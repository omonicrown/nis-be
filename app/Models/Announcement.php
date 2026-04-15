<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'priority',
        'visibility',
        'is_active',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForMembers($query)
    {
        return $query->whereIn('visibility', ['all', 'members_only']);
    }

    public function scopeForPublic($query)
    {
        return $query->where('visibility', 'all');
    }

    public function subgroups(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Subgroup::class, 'announcement_subgroup')
            ->withTimestamps();
    }

    /**
     * Scope: filter announcements for a specific user based on their subgroups.
     */
    public function scopeForUserSubgroups($query, $user)
    {
        return $query->where(function ($q) use ($user) {
            // Announcements with no subgroup filter (sent to everyone)
            $q->whereDoesntHave('subgroups')
                // OR announcements targeting user's subgroups
                ->orWhereHas('subgroups', function ($sq) use ($user) {
                    $sq->whereIn('subgroup_id', $user->subgroups->pluck('id'));
                });
        });
    }
}

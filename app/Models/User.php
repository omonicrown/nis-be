<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'other_names',
        'email',
        'phone',
        'gender',
        'avatar',
        'surcon_reg_no',
        'nis_membership_id',
        'suffix',
        'membership_category_id',
        'role_id',
        'status',
        'password',
        'is_migrated',
        'profile_completed',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_migrated' => 'boolean',
            'profile_completed' => 'boolean',
        ];
    }

    // ─── Accessors ──────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}" .
            ($this->other_names ? " {$this->other_names}" : ''));
    }

    public function getDesignationAttribute(): ?string
    {
        return $this->membershipCategory?->designation;
    }

    // ─── Relationships ──────────────────────────────────────

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function membershipCategory(): BelongsTo
    {
        return $this->belongsTo(MembershipCategory::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function subgroups(): BelongsToMany
    {
        return $this->belongsToMany(Subgroup::class, 'member_subgroup')
            ->withTimestamps();
    }

    public function executivePositions(): HasMany
    {
        return $this->hasMany(ExecutivePosition::class);
    }

    public function currentExecutivePosition(): HasOne
    {
        return $this->hasOne(ExecutivePosition::class)
            ->where('is_current', true);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', UserStatus::ACTIVE);
    }

    public function scopePending($query)
    {
        return $query->where('status', UserStatus::PENDING);
    }

    public function scopeByCategory($query, $categorySlug)
    {
        return $query->whereHas('membershipCategory', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('nis_membership_id', 'like', "%{$search}%")
              ->orWhere('surcon_reg_no', 'like', "%{$search}%");
        });
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return in_array($this->role?->slug, ['super_admin', 'admin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === 'super_admin';
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isSuperAdmin()) return true;

        return $this->role?->permissions()
            ->where('slug', $permissionSlug)
            ->exists() ?? false;
    }

    public function isApproved(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'description', 'details', 'banner_url',
        'banner_public_id', 'start_date', 'end_date', 'start_time',
        'end_time', 'venue', 'address', 'is_virtual', 'virtual_link',
        'type', 'status', 'requires_registration', 'max_attendees',
        'registration_fee', 'registration_deadline', 'created_by',
    ];

    protected $casts = [
            'start_date'            => 'date',
            'end_date'              => 'date',
            'is_virtual'            => 'boolean',
            'requires_registration' => 'boolean',
            'registration_fee'      => 'decimal:2',
            'registration_deadline' => 'datetime',
        ];

    protected static function booted(): void
    {
        static::creating(function ($event) {
            if (empty($event->slug)) {
                $event->slug = static::generateUniqueSlug($event->title);
            }
        });
    }

    // ─── Relationships ──────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', now()->toDateString())
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->orderBy('start_date');
    }

    public function scopePast($query)
    {
        return $query->where('start_date', '<', now()->toDateString())
            ->orWhere('status', 'completed')
            ->orderBy('start_date', 'desc');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('venue', 'like', "%{$search}%");
        });
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function registeredCount(): int
    {
        return $this->registrations()->where('status', 'registered')->count();
    }

    public function isFull(): bool
    {
        if (!$this->max_attendees) return false;
        return $this->registeredCount() >= $this->max_attendees;
    }

    public function isRegistrationOpen(): bool
    {
        if (!$this->requires_registration) return false;
        if ($this->isFull()) return false;
        if ($this->registration_deadline && $this->registration_deadline->isPast()) return false;
        return in_array($this->status, ['upcoming', 'ongoing']);
    }

    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $counter = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $counter++;
        }
        return $slug;
    }
}

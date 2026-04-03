<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'description', 'meeting_date', 'start_time', 'end_time',
        'venue', 'type', 'status', 'agenda', 'minutes_text',
        'minutes_file_url', 'minutes_file_public_id',
        'qr_code', 'qr_code_url', 'created_by',
    ];

    protected $casts = [
            'meeting_date' => 'date',
        ];

    // ─── Relationships ──────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopeUpcoming($query)
    {
        return $query->where('meeting_date', '>=', now()->toDateString())
            ->whereIn('status', ['scheduled', 'ongoing'])
            ->orderBy('meeting_date', 'asc');
    }

    public function scopePast($query)
    {
        return $query->where('meeting_date', '<', now()->toDateString())
            ->orWhere('status', 'completed')
            ->orderBy('meeting_date', 'desc');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByYear($query, int $year)
    {
        return $query->whereYear('meeting_date', $year);
    }

    public function scopeByMonth($query, int $month)
    {
        return $query->whereMonth('meeting_date', $month);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('agenda', 'like', "%{$search}%")
              ->orWhere('minutes_text', 'like', "%{$search}%");
        });
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function presentCount(): int
    {
        return $this->attendances()->where('status', 'present')->count();
    }

    public function absentCount(): int
    {
        return $this->attendances()->where('status', 'absent')->count();
    }

    public function apologyCount(): int
    {
        return $this->attendances()->where('status', 'apology')->count();
    }

    public function hasMinutes(): bool
    {
        return !empty($this->minutes_text) || !empty($this->minutes_file_url);
    }

    public function isUpcoming(): bool
    {
        return $this->meeting_date >= now()->toDateString() && $this->status === 'scheduled';
    }
}

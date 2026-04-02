<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ForumTopic extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'user_id', 'title', 'slug', 'body',
        'is_pinned', 'is_locked', 'views_count', 'last_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned'     => 'boolean',
            'is_locked'     => 'boolean',
            'last_reply_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($topic) {
            if (empty($topic->slug)) {
                $slug = Str::slug($topic->title);
                $original = $slug;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $original . '-' . $i++;
                }
                $topic->slug = $slug;
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'topic_id');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('body', 'like', "%{$search}%");
        });
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }
}

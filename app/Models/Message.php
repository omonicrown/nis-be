<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'sender_id', 'receiver_id', 'subject', 'body',
        'read_at', 'sender_deleted', 'receiver_deleted',
    ];

    protected $casts = [
            'read_at'          => 'datetime',
            'sender_deleted'   => 'boolean',
            'receiver_deleted' => 'boolean',
        ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('sender_id', $userId)->where('sender_deleted', false);
        })->orWhere(function ($q) use ($userId) {
            $q->where('receiver_id', $userId)->where('receiver_deleted', false);
        });
    }

    public function scopeInbox($query, int $userId)
    {
        return $query->where('receiver_id', $userId)->where('receiver_deleted', false);
    }

    public function scopeSent($query, int $userId)
    {
        return $query->where('sender_id', $userId)->where('sender_deleted', false);
    }

    public function scopeUnread($query, int $userId)
    {
        return $query->where('receiver_id', $userId)
            ->where('receiver_deleted', false)
            ->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}

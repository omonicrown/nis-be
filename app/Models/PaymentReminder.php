<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReminder extends Model
{
    protected $fillable = [
        'user_id', 'due_year', 'type', 'amount_due',
        'reminders_sent', 'last_reminder_at', 'is_paid', 'payment_id',
    ];

    protected $casts = [
            'amount_due'       => 'decimal:2',
            'last_reminder_at' => 'datetime',
            'is_paid'          => 'boolean',
        ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('due_year', $year);
    }
}

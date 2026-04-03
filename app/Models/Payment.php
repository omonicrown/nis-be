<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'type', 'description', 'amount', 'currency', 'method',
        'status', 'paystack_reference', 'paystack_access_code',
        'paystack_authorization_url', 'paystack_channel', 'paystack_metadata',
        'manual_reference', 'manual_proof_url', 'manual_proof_public_id',
        'manual_note', 'verified_by', 'verified_at', 'admin_note',
        'payment_year', 'payment_period', 'receipt_number', 'paid_at',
    ];

    protected $casts = [
            'amount'            => 'decimal:2',
            'paystack_metadata' => 'json',
            'verified_at'       => 'datetime',
            'paid_at'           => 'datetime',
        ];

    // ─── Relationships ──────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByYear($query, int $year)
    {
        return $query->where('payment_year', $year);
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }

    public function scopeManualPending($query)
    {
        return $query->whereIn('method', ['bank_transfer', 'cash'])
            ->where('status', 'pending');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('receipt_number', 'like', "%{$search}%")
              ->orWhere('paystack_reference', 'like', "%{$search}%")
              ->orWhere('manual_reference', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhereHas('user', fn($uq) => $uq->search($search));
        });
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isManual(): bool
    {
        return in_array($this->method, ['bank_transfer', 'cash', 'other']);
    }

    /**
     * Generate a unique receipt number.
     */
    public static function generateReceiptNumber(): string
    {
        $prefix = 'NIS';
        $year = date('Y');
        $latest = static::whereYear('created_at', $year)
            ->whereNotNull('receipt_number')
            ->count();

        return sprintf('%s-%s-%05d', $prefix, $year, $latest + 1);
    }
}

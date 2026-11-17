<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    public const TYPE_BOOKING_INVOICE = 'booking_invoice';

    public const TYPE_MONTHLY_INVOICE = 'monthly_invoice';

    public const TYPE_PAYMENT_RECEIPT = 'payment_receipt';

    public const STATE_DRAFT = 'DRAFT';

    public const STATE_APPROVED = 'APPROVED';

    public const STATE_VOIDED = 'VOIDED';

    protected $fillable = [
        'company_id',
        'type',
        'number',
        'booking_id',
        'payment_record_id',
        'period_start',
        'period_end',
        'total',
        'currency',
        'state',
        'prepared_by',
        'approved_by',
        'approved_at',
        'voided_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total' => 'decimal:2',
            'approved_at' => 'datetime',
            'voided_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentRecord::class, 'payment_record_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('state', self::STATE_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->state === self::STATE_APPROVED;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_BOOKING_INVOICE => 'Booking invoice',
            self::TYPE_MONTHLY_INVOICE => 'Monthly invoice',
            self::TYPE_PAYMENT_RECEIPT => 'Payment receipt',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function numberPrefix(): string
    {
        return $this->type === self::TYPE_PAYMENT_RECEIPT ? 'RCT' : 'INV';
    }
}

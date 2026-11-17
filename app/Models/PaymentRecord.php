<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentRecord extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_REJECTED = 'REJECTED';

    public const METHOD_CASH = 'CASH';

    public const METHOD_BANK = 'BANK';

    protected $fillable = [
        'company_id',
        'amount',
        'currency',
        'received_date',
        'method',
        'reference',
        'notes',
        'recorded_by',
        'status',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function allocatedAmount(): float
    {
        return round((float) $this->allocations()->sum('amount'), 2);
    }

    public function refundedAmount(): float
    {
        return round((float) $this->transactions()
            ->whereIn('type', [
                FinancialTransaction::TYPE_REFUND,
                FinancialTransaction::TYPE_CREDIT_ISSUED,
            ])
            ->sum('amount'), 2);
    }

    public function availableAmount(): float
    {
        return max(0, round((float) $this->amount - $this->allocatedAmount() - $this->refundedAmount(), 2));
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}

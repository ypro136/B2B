<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const SETTLEMENT_UNSETTLED = 'unsettled';

    public const SETTLEMENT_PARTIAL = 'partially_settled';

    public const SETTLEMENT_FULL = 'fully_settled';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'trip_id',
        'booked_by',
        'seats_count',
        'status',
        'unit_price',
        'total_amount',
        'settled_amount',
        'currency',
        'vat_enabled',
        'vat_rate',
        'settlement_status',
        'settled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats_count' => 'integer',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'settled_amount' => 'decimal:2',
            'vat_enabled' => 'boolean',
            'vat_rate' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * The company that owns the booking.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The booked trip.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * The company user that created the booking.
     */
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    /**
     * Assigned numbered seats.
     */
    public function seats(): HasMany
    {
        return $this->hasMany(BookingSeat::class)->orderBy('seat_number');
    }

    /**
     * Payment allocations applied to the booking.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Invoices and receipts linked to the booking.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Ledger entries linked to the booking.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /**
     * Scope to confirmed bookings.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Amount still due on the booking.
     */
    public function remainingDue(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->settled_amount, 2));
    }

    /**
     * Whether the booking is fully settled.
     */
    public function isFullySettled(): bool
    {
        return $this->settlement_status === self::SETTLEMENT_FULL;
    }
}

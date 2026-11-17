<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    public const TYPE_BOOKING_CHARGE = 'BOOKING_CHARGE';

    public const TYPE_PAYMENT_CONFIRMED = 'PAYMENT_CONFIRMED';

    public const TYPE_REFUND = 'REFUND';

    public const TYPE_CREDIT_ISSUED = 'CREDIT_ISSUED';

    public const TYPE_CREDIT_APPLIED = 'CREDIT_APPLIED';

    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    protected $fillable = [
        'company_id',
        'booking_id',
        'payment_record_id',
        'type',
        'amount',
        'currency',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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
}

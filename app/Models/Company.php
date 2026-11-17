<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'legal_name',
        'tax_number',
        'address',
        'contact',
        'seat_limit',
        'bonus_seats',
        'partial_payment_allowed',
        'quota_reset_at',
        'language',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seat_limit' => 'integer',
            'bonus_seats' => 'integer',
            'partial_payment_allowed' => 'boolean',
            'quota_reset_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Users that belong to the company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Bookings made by the company.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Seat requests submitted by the company.
     */
    public function seatRequests(): HasMany
    {
        return $this->hasMany(SeatRequest::class);
    }

    /**
     * Company-specific trip price overrides.
     */
    public function tripPrices(): HasMany
    {
        return $this->hasMany(CompanyTripPrice::class);
    }

    /**
     * Offline payment records.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentRecord::class);
    }

    /**
     * Credit movements for the company account.
     */
    public function creditMovements(): HasMany
    {
        return $this->hasMany(CompanyCreditMovement::class);
    }

    /**
     * Invoices and receipts issued to the company.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Named company contacts.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    /**
     * CRM notes.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CrmNote::class);
    }

    /**
     * CRM activities.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    /**
     * Determine whether the company can create another active user account.
     */
    public function canAddUser(): bool
    {
        return $this->users()->where('is_active', true)->count() < 2;
    }

    /**
     * Base plus approved one-time bonus seats.
     */
    public function effectiveSeatLimit(): int
    {
        return $this->seat_limit + $this->bonus_seats;
    }

    /**
     * Seats consumed since the latest quota reset.
     */
    public function usedSeats(): int
    {
        return (int) $this->bookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->when($this->quota_reset_at !== null, fn ($query) => $query->where('created_at', '>=', $this->quota_reset_at))
            ->sum('seats_count');
    }

    /**
     * Seats still available within the effective allowance.
     */
    public function availableSeats(): int
    {
        return max(0, $this->effectiveSeatLimit() - $this->usedSeats());
    }

    /**
     * Seats currently held by confirmed bookings on non-cancelled trips.
     */
    public function confirmedSeats(): int
    {
        return (int) $this->bookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereHas('trip', function ($query) {
                $query->where('status', '!=', Trip::STATUS_CANCELLED);
            })
            ->sum('seats_count');
    }

    /**
     * Determine whether the company has exhausted its seat allowance.
     */
    public function isAtSeatLimit(): bool
    {
        return $this->availableSeats() === 0;
    }

    /**
     * Account credit balance from immutable movements.
     */
    public function creditBalance(): float
    {
        $issued = (float) $this->creditMovements()->where('type', CompanyCreditMovement::TYPE_ISSUED)->sum('amount');
        $applied = (float) $this->creditMovements()->where('type', CompanyCreditMovement::TYPE_APPLIED)->sum('amount');

        return round($issued - $applied, 2);
    }

    /**
     * Outstanding balance across active bookings.
     */
    public function outstandingBalance(): float
    {
        return round((float) $this->bookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->selectRaw('COALESCE(SUM(total_amount - settled_amount), 0) as due')
            ->value('due'), 2);
    }

    /**
     * Determine whether a seat request is already waiting for review.
     */
    public function hasPendingSeatRequest(): bool
    {
        return $this->seatRequests()->pending()->exists();
    }
}

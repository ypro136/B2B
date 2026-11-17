<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Trip extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'SCHEDULED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_COMPLETED = 'COMPLETED';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'route_name',
        'origin',
        'destination',
        'departure_datetime',
        'departure_start',
        'total_seats',
        'standard_price',
        'per_seat_cost',
        'currency',
        'cutoff_hours',
        'template_id',
        'status',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'departure_datetime' => 'datetime',
            'departure_start' => 'datetime',
            'total_seats' => 'integer',
            'standard_price' => 'decimal:2',
            'per_seat_cost' => 'decimal:2',
            'cutoff_hours' => 'integer',
        ];
    }

    /**
     * Bookings made on the trip.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Numbered seat rows for the trip.
     */
    public function seats(): HasMany
    {
        return $this->hasMany(TripSeat::class)->orderBy('seat_number');
    }

    /**
     * Company-specific price overrides.
     */
    public function companyPrices(): HasMany
    {
        return $this->hasMany(CompanyTripPrice::class);
    }

    /**
     * The recurring template that generated the trip.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TripTemplate::class, 'template_id');
    }

    /**
     * The administrator that created the trip.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to trips that are still scheduled.
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope to scheduled trips that have not departed yet.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->scheduled()->where('departure_datetime', '>=', now());
    }

    /**
     * Booking cutoff hours: per-trip override or the global default.
     */
    public function cutoffHours(): int
    {
        return (int) ($this->cutoff_hours ?? Setting::int('booking_cutoff_hours', 2));
    }

    /**
     * The moment bookings and changes close.
     */
    public function bookingCutoffAt(): ?Carbon
    {
        return $this->departure_datetime?->copy()->subHours($this->cutoffHours());
    }

    /**
     * Seats already confirmed on the trip.
     */
    public function confirmedSeats(): int
    {
        return (int) $this->bookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->sum('seats_count');
    }

    /**
     * Seats that can still be booked on the trip.
     */
    public function remainingSeats(): int
    {
        return max(0, $this->total_seats - $this->confirmedSeats());
    }

    /**
     * Seat ids currently held by confirmed bookings.
     *
     * @return array<int, int>
     */
    public function occupiedSeatIds(): array
    {
        return BookingSeat::query()
            ->whereHas('booking', fn (Builder $query) => $query->where('status', Booking::STATUS_CONFIRMED))
            ->whereIn('trip_seat_id', $this->seats()->select('id'))
            ->pluck('trip_seat_id')
            ->all();
    }

    /**
     * Determine whether the trip accepts new bookings.
     */
    public function isBookable(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->departure_datetime !== null
            && $this->departure_datetime->isFuture()
            && now()->lt($this->bookingCutoffAt());
    }

    /**
     * Ensure numbered seat rows exist for the trip.
     */
    public function ensureSeats(): void
    {
        if ($this->seats()->exists()) {
            return;
        }

        $rows = [];

        for ($seat = 1; $seat <= $this->total_seats; $seat++) {
            $rows[] = [
                'trip_id' => $this->getKey(),
                'seat_number' => $seat,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->seats()->insert($rows);
    }
}

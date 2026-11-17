<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TripSeat extends Model
{
    protected $fillable = [
        'trip_id',
        'seat_number',
    ];

    protected function casts(): array
    {
        return [
            'seat_number' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function bookingSeat(): HasOne
    {
        return $this->hasOne(BookingSeat::class);
    }

    public function isTaken(): bool
    {
        return $this->bookingSeat()
            ->whereHas('booking', fn ($query) => $query->where('status', Booking::STATUS_CONFIRMED))
            ->exists();
    }
}

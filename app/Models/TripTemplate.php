<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripTemplate extends Model
{
    protected $fillable = [
        'route_name',
        'origin',
        'destination',
        'departure_time',
        'weekdays',
        'total_seats',
        'standard_price',
        'per_seat_cost',
        'valid_from',
        'valid_until',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_seats' => 'integer',
            'standard_price' => 'decimal:2',
            'per_seat_cost' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return list<int>
     */
    public function weekdayNumbers(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', $this->weekdays))));
    }

    public function runsOn(int $weekday): bool
    {
        return in_array($weekday, $this->weekdayNumbers(), true);
    }
}

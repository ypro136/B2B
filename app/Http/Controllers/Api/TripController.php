<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class TripController extends Controller
{
    /**
     * List upcoming trips that are open for booking.
     */
    public function index(): JsonResponse
    {
        $trips = Trip::query()
            ->upcoming()
            ->withSum([
                'bookings as confirmed_seats_count' => fn (Builder $query) => $query
                    ->where('status', Booking::STATUS_CONFIRMED),
            ], 'seats_count')
            ->orderBy('departure_datetime')
            ->get()
            ->map(fn (Trip $trip) => [
                'id' => $trip->getKey(),
                'route_name' => $trip->route_name,
                'origin' => $trip->origin,
                'destination' => $trip->destination,
                'departure_datetime' => $trip->departure_datetime?->toIso8601String(),
                'total_seats' => $trip->total_seats,
                'seats_booked' => (int) $trip->confirmed_seats_count,
                'seats_remaining' => max(0, $trip->total_seats - (int) $trip->confirmed_seats_count),
            ]);

        return response()->json(['data' => $trips]);
    }
}

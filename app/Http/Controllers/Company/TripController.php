<?php

namespace App\Http\Controllers\Company;

use App\Models\Booking;
use App\Models\Trip;
use App\Services\PricingService;
use Illuminate\Contracts\View\View;

class TripController extends BaseController
{
    /**
     * List upcoming trips that can be booked.
     */
    public function index(): View
    {
        return view('company::trips.index', [
            'company' => auth()->user()?->company,
            'trips' => Trip::query()
                ->upcoming()
                ->withSum([
                    'bookings as confirmed_seats_count' => fn ($query) => $query->where('status', Booking::STATUS_CONFIRMED),
                ], 'seats_count')
                ->orderBy('departure_datetime')
                ->paginate(15),
        ]);
    }

    /**
     * Show the numbered seat picker for one trip.
     */
    public function show(Trip $trip, PricingService $pricing): View
    {
        $company = auth()->user()?->company;
        abort_if($company === null, 403);

        // Numbered seat selection disabled for the demo: the trip page no longer renders the seat picker.
        // $trip->ensureSeats();
        // $trip->load(['seats.bookingSeat.booking.company']);
        //
        // $ownSeatIds = $company->bookings()
        //     ->where('trip_id', $trip->getKey())
        //     ->where('status', Booking::STATUS_CONFIRMED)
        //     ->with('seats')
        //     ->get()
        //     ->flatMap(fn (Booking $booking) => $booking->seats->pluck('trip_seat_id'))
        //     ->all();

        return view('company::trips.show', [
            'company' => $company,
            'trip' => $trip,
            'unitPrice' => $pricing->unitPriceFor($company, $trip),
            // 'occupied' => $trip->occupiedSeatIds(),
            // 'ownSeatIds' => $ownSeatIds,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Company;

use App\Models\Booking;
use App\Models\Trip;
use App\Services\BookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends BaseController
{
    /**
     * List the company's bookings.
     */
    public function index(): View
    {
        $company = auth()->user()?->company;

        return view('company::bookings.index', [
            'company' => $company,
            'bookings' => $company?->bookings()
                ->with(['trip', 'bookedBy', 'seats'])
                ->latest()
                ->paginate(15) ?? collect(),
        ]);
    }

    /**
     * Show a booking with its seats and settlement history.
     */
    public function show(Booking $booking): View
    {
        $this->authorizeBooking($booking);

        $booking->load(['trip.seats', 'seats', 'bookedBy', 'allocations.payment', 'documents']);

        // Numbered seat selection disabled for the demo: freeSeats was only used by the change-seat form.
        // $freeSeats = $booking->trip->seats()
        //     ->whereNotIn('id', $booking->trip->occupiedSeatIds())
        //     ->orderBy('seat_number')
        //     ->pluck('seat_number');

        return view('company::bookings.show', [
            'booking' => $booking,
            // 'freeSeats' => $freeSeats,
        ]);
    }

    /**
     * Book specific seats on a trip for the signed-in user's company.
     */
    public function store(Request $request, BookingService $bookings): RedirectResponse
    {
        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            // Numbered seat selection disabled for the demo: booking is by number of seats only.
            // 'seats' => ['nullable', 'array'],
            // 'seats.*' => ['integer', 'min:1'],
            'seats_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $company = $request->user()->company;

        if ($company === null) {
            throw ValidationException::withMessages([
                'trip_id' => 'This account is not linked to a company.',
            ]);
        }

        $trip = Trip::query()->findOrFail($data['trip_id']);

        // Numbered seat selection disabled for the demo: book by number of seats needed.
        // if (! empty($data['seats'])) {
        //     $booking = $bookings->bookSeats($company, $trip, $request->user(), $data['seats']);
        // } else {
            $booking = $bookings->book($company, $trip, $request->user(), (int) ($data['seats_count'] ?? 1));
        // }

        return redirect()
            ->route('company.bookings.show', $booking)
            ->with('status', 'Seats booked successfully: '.$booking->seats->pluck('seat_number')->implode(', ').'.');
    }

    /**
     * Cancel one of the company's bookings.
     */
    public function cancel(Request $request, Booking $booking, BookingService $bookings): RedirectResponse
    {
        $this->authorizeBooking($booking);

        $bookings->cancel($booking, $request->user());

        return back()->with('status', 'Booking cancelled and seats released.');
    }

    // Change-a-seat disabled for the demo: booking is by number of seats only.
    // /**
    //  * Move a booked seat to a free seat on the same trip.
    //  */
    // public function changeSeat(Request $request, Booking $booking, BookingService $bookings): RedirectResponse
    // {
    //     $this->authorizeBooking($booking);
    //
    //     $data = $request->validate([
    //         'from_seat' => ['required', 'integer', 'min:1'],
    //         'to_seat' => ['required', 'integer', 'min:1', 'different:from_seat'],
    //     ]);
    //
    //     $bookings->changeSeat($booking, (int) $data['from_seat'], (int) $data['to_seat'], $request->user());
    //
    //     return back()->with('status', 'Seat changed to '.$data['to_seat'].'.');
    // }

    private function authorizeBooking(Booking $booking): void
    {
        abort_unless(
            (int) $booking->company_id === (int) auth()->user()?->company_id,
            403,
            'This booking belongs to another company.',
        );
    }
}

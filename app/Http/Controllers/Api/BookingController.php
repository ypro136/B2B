<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * List bookings for the authenticated user's company.
     */
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->where('company_id', $request->user()->company_id)
            ->with('trip')
            ->latest()
            ->get()
            ->map(fn (Booking $booking) => $this->bookingPayload($booking));

        return response()->json(['data' => $bookings]);
    }

    /**
     * Book seats on a trip for the authenticated user's company.
     */
    public function store(Request $request, BookingService $bookings): JsonResponse
    {
        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'seats_count' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $trip = Trip::query()->findOrFail($data['trip_id']);

        $booking = $bookings->book(
            $request->user()->company,
            $trip,
            $request->user(),
            (int) $data['seats_count'],
        );

        return response()->json([
            'message' => 'Seats booked.',
            'booking' => $this->bookingPayload($booking->load('trip')),
        ], 201);
    }

    /**
     * Cancel a booking owned by the authenticated user's company.
     */
    public function cancel(Request $request, Booking $booking, BookingService $bookings): JsonResponse
    {
        abort_unless(
            (int) $booking->company_id === (int) $request->user()->company_id,
            403,
            'This booking does not belong to your company.',
        );

        $bookings->cancel($booking, $request->user());

        return response()->json([
            'message' => 'Booking cancelled.',
            'booking' => $this->bookingPayload($booking->fresh()->load('trip')),
        ]);
    }

    /**
     * Shape a booking for API responses.
     *
     * @return array<string, mixed>
     */
    private function bookingPayload(Booking $booking): array
    {
        return [
            'id' => $booking->getKey(),
            'seats_count' => $booking->seats_count,
            'status' => $booking->status,
            'created_at' => $booking->created_at?->toIso8601String(),
            'trip' => $booking->trip === null ? null : [
                'id' => $booking->trip->getKey(),
                'route_name' => $booking->trip->route_name,
                'origin' => $booking->trip->origin,
                'destination' => $booking->trip->destination,
                'departure_datetime' => $booking->trip->departure_datetime?->toIso8601String(),
                'status' => $booking->trip->status,
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Trip;
use App\Models\TripSeat;
use App\Services\PricingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripController extends BaseController
{
    /**
     * List all trips with their confirmed seat counts.
     */
    public function index(): View
    {
        return view('admin::trips.index', [
            'trips' => Trip::query()
                ->with('creator')
                ->withCount('seats')
                ->withSum([
                    'bookings as confirmed_seats_count' => fn ($query) => $query->where('status', Booking::STATUS_CONFIRMED),
                ], 'seats_count')
                ->orderByDesc('departure_datetime')
                ->paginate(15),
        ]);
    }

    /**
     * Show the trip creation form.
     */
    public function create(): View
    {
        return view('admin::trips.create', [
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Schedule a new trip with numbered seat rows.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:160'],
            'origin' => ['required', 'string', 'max:160'],
            'destination' => ['required', 'string', 'max:160'],
            'departure_datetime' => ['required', 'date', 'after:now'],
            'total_seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'standard_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'per_seat_cost' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'cutoff_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
        ]);

        $trip = DB::transaction(function () use ($data, $request) {
            $trip = Trip::query()->create([
                'route_name' => $data['route_name'],
                'origin' => $data['origin'],
                'destination' => $data['destination'],
                'departure_datetime' => $data['departure_datetime'],
                'total_seats' => (int) $data['total_seats'],
                'standard_price' => (float) ($data['standard_price'] ?? 0),
                'per_seat_cost' => (float) ($data['per_seat_cost'] ?? 0),
                'currency' => 'EGP',
                'cutoff_hours' => $data['cutoff_hours'] ?? null,
                'status' => Trip::STATUS_SCHEDULED,
                'created_by' => $request->user()->getKey(),
            ]);

            $trip->ensureSeats();

            AuditLog::record($trip, 'trip.created', $request->user(), null, [
                'route_name' => $trip->route_name,
                'origin' => $trip->origin,
                'destination' => $trip->destination,
                'departure_datetime' => $trip->departure_datetime?->toIso8601String(),
                'total_seats' => $trip->total_seats,
                'standard_price' => $trip->standard_price,
                'per_seat_cost' => $trip->per_seat_cost,
                'status' => $trip->status,
            ]);

            return $trip;
        });

        return redirect()
            ->route('admin.trips.show', $trip)
            ->with('status', 'Trip scheduled with '.$trip->total_seats.' numbered seats.');
    }

    /**
     * Show a trip with its seat map and affected bookings.
     */
    public function show(Trip $trip): View
    {
        $trip->ensureSeats();

        $trip->load([
            'seats.bookingSeat.booking.company',
            'bookings.company',
            'bookings.bookedBy',
            'bookings.seats',
            'companyPrices.company',
        ]);

        return view('admin::trips.show', [
            'trip' => $trip,
            'companies' => Company::query()->orderBy('name')->get(),
            'occupied' => $trip->occupiedSeatIds(),
        ]);
    }

    /**
     * Show the trip edit form.
     */
    public function edit(Trip $trip): View
    {
        return view('admin::trips.edit', [
            'trip' => $trip,
        ]);
    }

    /**
     * Update trip details, pricing and cutoff.
     */
    public function update(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:160'],
            'origin' => ['required', 'string', 'max:160'],
            'destination' => ['required', 'string', 'max:160'],
            'departure_datetime' => ['required', 'date'],
            'total_seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'standard_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'per_seat_cost' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'cutoff_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
        ]);

        $old = $trip->only([
            'route_name', 'origin', 'destination', 'departure_datetime',
            'total_seats', 'standard_price', 'per_seat_cost', 'cutoff_hours',
        ]);

        DB::transaction(function () use ($trip, $data, $request) {
            $newTotal = (int) $data['total_seats'];

            if ($newTotal < $trip->confirmedSeats()) {
                throw ValidationException::withMessages([
                    'total_seats' => 'Capacity cannot drop below the '.$trip->confirmedSeats().' seat(s) already booked.',
                ]);
            }

            if ($newTotal !== $trip->total_seats) {
                $this->resizeSeats($trip, $newTotal);
            }

            $trip->update([
                'route_name' => $data['route_name'],
                'origin' => $data['origin'],
                'destination' => $data['destination'],
                'departure_datetime' => $data['departure_datetime'],
                'total_seats' => $newTotal,
                'standard_price' => (float) $data['standard_price'],
                'per_seat_cost' => (float) $data['per_seat_cost'],
                'cutoff_hours' => $data['cutoff_hours'] ?? null,
            ]);

            AuditLog::record($trip, 'trip.updated', $request->user(), $old, $trip->only([
                'route_name', 'origin', 'destination', 'departure_datetime',
                'total_seats', 'standard_price', 'per_seat_cost', 'cutoff_hours',
            ]));
        });

        return redirect()
            ->route('admin.trips.show', $trip)
            ->with('status', 'Trip updated.');
    }

    /**
     * Set a company-specific final price for this trip.
     */
    public function setCompanyPrice(Request $request, Trip $trip, PricingService $pricing): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        $pricing->setOverride($company, $trip, (float) $data['price'], $request->user());

        return back()->with('status', 'Company price override saved for '.$company->name.'.');
    }

    /**
     * Set the per-seat cost used by profit reporting.
     */
    public function setCost(Request $request, Trip $trip, PricingService $pricing): RedirectResponse
    {
        $data = $request->validate([
            'per_seat_cost' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $pricing->setCost($trip, (float) $data['per_seat_cost'], $request->user());

        return back()->with('status', 'Per-seat cost updated.');
    }

    /**
     * Cancel a scheduled trip and release its bookings.
     */
    public function cancel(Request $request, Trip $trip): RedirectResponse
    {
        if ($trip->status !== Trip::STATUS_SCHEDULED) {
            return back()->withErrors(['trip' => 'Only scheduled trips can be cancelled.']);
        }

        DB::transaction(function () use ($trip, $request) {
            $bookings = $trip->bookings()->where('status', Booking::STATUS_CONFIRMED)->get();

            foreach ($bookings as $booking) {
                $booking->seats()->delete();
                $booking->update(['status' => Booking::STATUS_CANCELLED]);
            }

            $trip->update(['status' => Trip::STATUS_CANCELLED]);

            AuditLog::record($trip, 'trip.cancelled', $request->user(), [
                'status' => Trip::STATUS_SCHEDULED,
            ], [
                'status' => Trip::STATUS_CANCELLED,
                'affected_bookings' => $bookings->count(),
            ]);
        });

        return back()->with('status', 'Trip cancelled and seats released.');
    }

    /**
     * Mark a departed trip as completed.
     */
    public function complete(Request $request, Trip $trip): RedirectResponse
    {
        if ($trip->status !== Trip::STATUS_SCHEDULED) {
            return back()->withErrors(['trip' => 'Only scheduled trips can be completed.']);
        }

        if ($trip->departure_datetime !== null && $trip->departure_datetime->isFuture()) {
            return back()->withErrors(['trip' => 'A trip cannot be completed before its departure time.']);
        }

        DB::transaction(function () use ($trip, $request) {
            $trip->update(['status' => Trip::STATUS_COMPLETED]);

            AuditLog::record($trip, 'trip.completed', $request->user(), [
                'status' => Trip::STATUS_SCHEDULED,
            ], [
                'status' => Trip::STATUS_COMPLETED,
            ]);
        });

        return back()->with('status', 'Trip marked as completed.');
    }

    /**
     * Grow or shrink the numbered seat rows while protecting booked seats.
     */
    private function resizeSeats(Trip $trip, int $newTotal): void
    {
        if ($newTotal > $trip->total_seats) {
            $rows = [];

            for ($seat = $trip->total_seats + 1; $seat <= $newTotal; $seat++) {
                $rows[] = [
                    'trip_id' => $trip->getKey(),
                    'seat_number' => $seat,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $trip->seats()->insert($rows);

            return;
        }

        $occupiedBeyond = TripSeat::query()
            ->where('trip_id', $trip->getKey())
            ->where('seat_number', '>', $newTotal)
            ->whereHas('bookingSeat', fn ($query) => $query->whereHas('booking', fn ($booking) => $booking->where('status', Booking::STATUS_CONFIRMED)))
            ->exists();

        if ($occupiedBeyond) {
            throw ValidationException::withMessages([
                'total_seats' => 'Seats above the new capacity are still occupied. Move or cancel those bookings first.',
            ]);
        }

        TripSeat::query()
            ->where('trip_id', $trip->getKey())
            ->where('seat_number', '>', $newTotal)
            ->delete();
    }
}

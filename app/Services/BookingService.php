<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Company;
use App\Models\FinancialTransaction;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripSeat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private PricingService $pricing) {}

    /**
     * Book a number of seats, auto-assigning the next free numbered seats.
     *
     * @throws ValidationException
     */
    public function book(Company $company, Trip $trip, User $bookedBy, int $seats): Booking
    {
        $trip = Trip::query()->findOrFail($trip->getKey());
        $trip->ensureSeats();

        $occupied = $trip->occupiedSeatIds();

        $numbers = $trip->seats()
            ->whereNotIn('id', $occupied)
            ->limit($seats)
            ->pluck('seat_number')
            ->all();

        if (count($numbers) < $seats) {
            throw ValidationException::withMessages([
                'seats_count' => 'Not enough free seats remain on this trip.',
            ]);
        }

        return $this->bookSeats($company, $trip, $bookedBy, $numbers, 'seats_count');
    }

    /**
     * Book specific numbered seats for the acting company.
     *
     * @param  list<int>  $seatNumbers
     *
     * @throws ValidationException
     */
    public function bookSeats(Company $company, Trip $trip, User $bookedBy, array $seatNumbers, string $errorKey = 'seats'): Booking
    {
        $seatNumbers = array_values(array_unique(array_map('intval', $seatNumbers)));
        sort($seatNumbers);

        if ($seatNumbers === []) {
            throw ValidationException::withMessages([
                "{$errorKey}" => 'Choose at least one seat.',
            ]);
        }

        return DB::transaction(function () use ($company, $trip, $bookedBy, $seatNumbers, $errorKey) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->getKey());
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->getKey());
            $trip->ensureSeats();

            if ((int) $bookedBy->company_id !== (int) $company->getKey()) {
                throw ValidationException::withMessages([
                    'trip_id' => 'The booking user does not belong to this company.',
                ]);
            }

            if (! $company->is_active) {
                throw ValidationException::withMessages([
                    'trip_id' => 'This company account is inactive.',
                ]);
            }

            if (! $trip->isBookable()) {
                throw ValidationException::withMessages([
                    'trip_id' => $trip->status === Trip::STATUS_SCHEDULED
                        ? 'Bookings are closed for this trip (cutoff is '.$trip->cutoffHours().' hour(s) before departure).'
                        : 'The selected trip is not open for booking.',
                ]);
            }

            $seats = TripSeat::query()
                ->where('trip_id', $trip->getKey())
                ->whereIn('seat_number', $seatNumbers)
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($seatNumbers)) {
                throw ValidationException::withMessages([
                    "{$errorKey}" => 'One or more selected seats do not exist on this trip.',
                ]);
            }

            $occupied = BookingSeat::query()
                ->whereIn('trip_seat_id', $seats->pluck('id'))
                ->whereHas('booking', fn ($query) => $query->where('status', Booking::STATUS_CONFIRMED))
                ->exists();

            if ($occupied) {
                throw ValidationException::withMessages([
                    "{$errorKey}" => 'One or more selected seats have just been taken. Please choose again.',
                ]);
            }

            $count = count($seatNumbers);
            $allowance = $company->availableSeats();

            if ($count > $allowance) {
                throw ValidationException::withMessages([
                    "{$errorKey}" => "The company can only book {$allowance} more seat(s) within its current allowance.",
                ]);
            }

            $unitPrice = $this->pricing->unitPriceFor($company, $trip);
            $total = round($unitPrice * $count, 2);
            $currency = $trip->currency ?: Setting::get('currency', 'EGP');

            $booking = Booking::query()->create([
                'company_id' => $company->getKey(),
                'trip_id' => $trip->getKey(),
                'booked_by' => $bookedBy->getKey(),
                'seats_count' => $count,
                'status' => Booking::STATUS_CONFIRMED,
                'unit_price' => $unitPrice,
                'total_amount' => $total,
                'settled_amount' => 0,
                'currency' => $currency,
                'vat_enabled' => Setting::bool('vat_enabled', true),
                'vat_rate' => Setting::float('vat_rate', 14),
                'settlement_status' => Booking::SETTLEMENT_UNSETTLED,
            ]);

            foreach ($seats as $seat) {
                BookingSeat::query()->create([
                    'booking_id' => $booking->getKey(),
                    'trip_seat_id' => $seat->getKey(),
                    'seat_number' => $seat->seat_number,
                ]);
            }

            FinancialTransaction::query()->create([
                'company_id' => $company->getKey(),
                'booking_id' => $booking->getKey(),
                'type' => FinancialTransaction::TYPE_BOOKING_CHARGE,
                'amount' => $total,
                'currency' => $currency,
                'description' => 'Booking charge: '.$trip->route_name.' ('.implode(', ', $seatNumbers).')',
                'created_by' => $bookedBy->getKey(),
            ]);

            AuditLog::record($booking, 'booking.created', $bookedBy, null, [
                'trip_id' => $trip->getKey(),
                'company_id' => $company->getKey(),
                'seats_count' => $count,
                'seat_numbers' => $seatNumbers,
                'unit_price' => $unitPrice,
                'total_amount' => $total,
                'status' => Booking::STATUS_CONFIRMED,
            ]);

            return $booking->load('seats');
        });
    }

    /**
     * Cancel a confirmed booking and release its seats.
     *
     * @throws ValidationException
     */
    public function cancel(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $trip = Trip::query()->findOrFail($booking->trip_id);

            if ($booking->status !== Booking::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'booking' => 'Only confirmed bookings can be cancelled.',
                ]);
            }

            if ($trip->status === Trip::STATUS_SCHEDULED && now()->gte($trip->bookingCutoffAt())) {
                throw ValidationException::withMessages([
                    'booking' => 'Cancellations are closed for this trip (cutoff is '.$trip->cutoffHours().' hour(s) before departure).',
                ]);
            }

            $seatNumbers = $booking->seats()->pluck('seat_number')->all();
            $booking->seats()->delete();
            $booking->update(['status' => Booking::STATUS_CANCELLED]);

            AuditLog::record($booking, 'booking.cancelled', $actor, [
                'status' => Booking::STATUS_CONFIRMED,
            ], [
                'status' => Booking::STATUS_CANCELLED,
                'released_seats' => $seatNumbers,
            ]);

            return $booking->refresh();
        });
    }

    /**
     * Move one booked seat to a free seat on the same trip.
     *
     * @throws ValidationException
     */
    public function changeSeat(Booking $booking, int $fromSeat, int $toSeat, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $fromSeat, $toSeat, $actor) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $trip = Trip::query()->lockForUpdate()->findOrFail($booking->trip_id);

            if ($booking->status !== Booking::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'seat' => 'Only confirmed bookings can change seats.',
                ]);
            }

            if (now()->gte($trip->bookingCutoffAt())) {
                throw ValidationException::withMessages([
                    'seat' => 'Seat changes are closed for this trip (cutoff is '.$trip->cutoffHours().' hour(s) before departure).',
                ]);
            }

            $current = $booking->seats()->where('seat_number', $fromSeat)->first();

            if ($current === null) {
                throw ValidationException::withMessages([
                    'seat' => 'The selected seat is not part of this booking.',
                ]);
            }

            $target = TripSeat::query()
                ->where('trip_id', $trip->getKey())
                ->where('seat_number', $toSeat)
                ->lockForUpdate()
                ->first();

            if ($target === null) {
                throw ValidationException::withMessages([
                    'seat' => 'The target seat does not exist on this trip.',
                ]);
            }

            $taken = BookingSeat::query()
                ->where('trip_seat_id', $target->getKey())
                ->whereHas('booking', fn ($query) => $query->where('status', Booking::STATUS_CONFIRMED))
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'seat' => 'The target seat is already taken.',
                ]);
            }

            $current->update([
                'trip_seat_id' => $target->getKey(),
                'seat_number' => $target->seat_number,
            ]);

            AuditLog::record($booking, 'booking.seat_changed', $actor, [
                'seat_number' => $fromSeat,
            ], [
                'seat_number' => $target->seat_number,
            ]);

            return $booking->refresh()->load('seats');
        });
    }
}

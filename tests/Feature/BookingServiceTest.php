<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Trip;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_a_company_can_book_seats_within_both_limits(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        $booking = app(BookingService::class)->book($company, $trip, $user, 3);

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertSame(3, $booking->seats_count);
        $this->assertSame(3, $company->fresh()->confirmedSeats());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'booking.created',
            'auditable_id' => $booking->id,
        ]);
    }

    public function test_booking_cannot_exceed_the_company_seat_limit(): void
    {
        $company = $this->makeCompany('Acme Logistics', 2);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        try {
            app(BookingService::class)->book($company, $trip, $user, 3);
            $this->fail('Booking above the company limit should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('seats_count', $exception->errors());
        }

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_cannot_exceed_the_trip_capacity(): void
    {
        $company = $this->makeCompany('Acme Logistics', 50);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 2]);

        try {
            app(BookingService::class)->book($company, $trip, $user, 3);
            $this->fail('Booking above the trip capacity should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('seats_count', $exception->errors());
        }

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_cancelled_trips_cannot_be_booked(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['status' => Trip::STATUS_CANCELLED]);

        try {
            app(BookingService::class)->book($company, $trip, $user, 1);
            $this->fail('A cancelled trip should not accept bookings.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('trip_id', $exception->errors());
        }
    }

    public function test_past_trips_cannot_be_booked(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['departure_datetime' => now()->subDay()]);

        try {
            app(BookingService::class)->book($company, $trip, $user, 1);
            $this->fail('A departed trip should not accept bookings.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('trip_id', $exception->errors());
        }
    }

    public function test_cancelling_a_booking_releases_the_seats(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        $booking = app(BookingService::class)->book($company, $trip, $user, 4);
        $this->assertSame(4, $company->fresh()->confirmedSeats());

        app(BookingService::class)->cancel($booking, $user);

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(0, $company->fresh()->confirmedSeats());
        $this->assertSame(20, $trip->fresh()->remainingSeats());
    }
}

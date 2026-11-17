<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\SeatRequest;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class ApiBookingTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_company_user_can_list_upcoming_trips(): void
    {
        $this->makeTrip([
            'route_name' => 'Upcoming Route',
            'departure_datetime' => now()->addDays(2),
        ]);
        $this->makeTrip([
            'route_name' => 'Past Route',
            'departure_datetime' => now()->subDay(),
        ]);
        $this->makeTrip([
            'route_name' => 'Cancelled Route',
            'departure_datetime' => now()->addDays(3),
            'status' => Trip::STATUS_CANCELLED,
        ]);

        $response = $this->withToken($this->tokenFor($this->makeCompanyUser($this->makeCompany())))
            ->getJson('/api/trips');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.route_name', 'Upcoming Route')
            ->assertJsonPath('data.0.seats_remaining', 40);
    }

    public function test_company_user_can_book_seats(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        $response = $this->withToken($this->tokenFor($user))->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'seats_count' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('booking.status', Booking::STATUS_CONFIRMED)
            ->assertJsonPath('booking.seats_count', 2)
            ->assertJsonPath('booking.trip.id', $trip->id);

        $this->assertDatabaseHas('bookings', [
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'seats_count' => 2,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_api_enforces_the_company_seat_limit(): void
    {
        $company = $this->makeCompany('Acme Logistics', 3);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        $this->withToken($this->tokenFor($user))->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'seats_count' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors('seats_count');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_api_enforces_trip_capacity(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 2]);

        $this->withToken($this->tokenFor($user))->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'seats_count' => 3,
        ])->assertStatus(422)->assertJsonValidationErrors('seats_count');
    }

    public function test_bookings_are_scoped_to_the_company(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $otherCompany = $this->makeCompany('Beta Freight', 10);
        $otherUser = $this->makeCompanyUser($otherCompany);
        $trip = $this->makeTrip(['total_seats' => 20]);

        $booking = app(BookingService::class)->book($otherCompany, $trip, $otherUser, 2);

        $response = $this->withToken($this->tokenFor($this->makeCompanyUser($company)))
            ->getJson('/api/bookings');

        $response->assertOk()->assertJsonCount(0, 'data');

        $this->withToken($this->tokenFor($this->makeCompanyUser($company)))
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertForbidden();

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_company_user_can_cancel_their_own_booking(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        $booking = app(BookingService::class)->book($company, $trip, $user, 2);

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_CANCELLED);

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function test_company_user_can_submit_and_list_seat_requests(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);

        $this->withToken($this->tokenFor($user))->postJson('/api/seat-requests', [
            'requested_seats' => 5,
            'reason' => 'New contract',
        ])->assertCreated()
            ->assertJsonPath('seat_request.status', SeatRequest::STATUS_PENDING)
            ->assertJsonPath('seat_request.requested_seats', 5);

        $this->withToken($this->tokenFor($user))->postJson('/api/seat-requests', [
            'requested_seats' => 3,
        ])->assertStatus(422)->assertJsonValidationErrors('requested_seats');

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/seat-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', SeatRequest::STATUS_PENDING);
    }

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');
    }
}

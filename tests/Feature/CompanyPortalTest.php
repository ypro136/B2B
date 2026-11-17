<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\SeatRequest;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class CompanyPortalTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_company_portal_pages_render(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $this->makeTrip(['total_seats' => 20]);

        $this->actingAs($user);

        $this->withoutVite()->get(route('company.dashboard'))->assertOk();
        $this->withoutVite()->get(route('company.trips.index'))->assertOk();
        $this->withoutVite()->get(route('company.bookings.index'))->assertOk();
        $this->withoutVite()->get(route('company.seat-requests.index'))->assertOk();
    }

    public function test_a_company_user_can_book_seats_through_the_portal(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        $response = $this->actingAs($user)->post(route('company.bookings.store'), [
            'trip_id' => $trip->id,
            'seats_count' => 2,
        ]);

        $response->assertRedirect(route('company.bookings.show', \App\Models\Booking::query()->first()));
        $this->assertDatabaseHas('bookings', [
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'seats_count' => 2,
            'status' => 'CONFIRMED',
        ]);
    }

    public function test_a_company_user_can_cancel_their_own_booking(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        $booking = app(BookingService::class)->book($company, $trip, $user, 2);

        $this->actingAs($user)
            ->post(route('company.bookings.cancel', $booking))
            ->assertRedirect();

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function test_a_company_user_cannot_cancel_another_companys_booking(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $otherCompany = $this->makeCompany('Beta Freight', 10);
        $otherUser = $this->makeCompanyUser($otherCompany);
        $trip = $this->makeTrip(['total_seats' => 20]);

        $booking = app(BookingService::class)->book($otherCompany, $trip, $otherUser, 2);

        $this->actingAs($this->makeCompanyUser($company))
            ->post(route('company.bookings.cancel', $booking))
            ->assertForbidden();
    }

    public function test_a_company_user_can_submit_a_seat_request_through_the_portal(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);

        $response = $this->actingAs($user)->post(route('company.seat-requests.store'), [
            'requested_seats' => 5,
            'reason' => 'New contract',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('seat_requests', [
            'company_id' => $company->id,
            'requested_seats' => 5,
            'status' => SeatRequest::STATUS_PENDING,
        ]);
    }
}

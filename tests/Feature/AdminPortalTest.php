<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Booking;
use App\Models\Role;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_super_admin_can_schedule_a_trip(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.trips.store'), [
            'route_name' => 'Abuja to Kano',
            'origin' => 'Abuja',
            'destination' => 'Kano',
            'departure_datetime' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'total_seats' => 30,
        ]);

        $response->assertRedirect(route('admin.trips.show', \App\Models\Trip::query()->first()));
        $this->assertDatabaseHas('trips', [
            'route_name' => 'Abuja to Kano',
            'total_seats' => 30,
            'status' => 'SCHEDULED',
        ]);
    }

    public function test_regular_admin_without_permission_cannot_schedule_a_trip(): void
    {
        $admin = $this->makeAdmin(Role::ADMIN);

        $this->actingAs($admin)->post(route('admin.trips.store'), [
            'route_name' => 'Abuja to Kano',
            'origin' => 'Abuja',
            'destination' => 'Kano',
            'departure_datetime' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'total_seats' => 30,
        ])->assertForbidden();
    }

    public function test_regular_admin_with_manage_trips_can_schedule_a_trip(): void
    {
        $superAdmin = $this->makeAdmin();
        $admin = $this->makeAdmin(Role::ADMIN);

        AdminPermission::query()->create([
            'admin_user_id' => $admin->id,
            'feature' => AdminPermission::MANAGE_TRIPS,
            'granted_by' => $superAdmin->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.trips.store'), [
            'route_name' => 'Abuja to Kano',
            'origin' => 'Abuja',
            'destination' => 'Kano',
            'departure_datetime' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'total_seats' => 30,
        ])->assertRedirect(route('admin.trips.show', \App\Models\Trip::query()->first()));
    }

    public function test_seat_limit_updates_require_the_feature_permission(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $admin = $this->makeAdmin(Role::ADMIN);

        $this->actingAs($admin)
            ->put(route('admin.companies.update', $company), ['seat_limit' => 15])
            ->assertForbidden();

        AdminPermission::query()->create([
            'admin_user_id' => $admin->id,
            'feature' => AdminPermission::MANAGE_SEAT_LIMITS,
            'granted_by' => $this->makeAdmin()->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.companies.update', $company), ['seat_limit' => 15])
            ->assertRedirect(route('admin.companies.edit', $company));

        $this->assertSame(15, $company->fresh()->seat_limit);
    }

    public function test_seat_limit_cannot_drop_below_seats_in_use(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);
        $admin = $this->makeAdmin();

        app(BookingService::class)->book($company, $trip, $user, 4);

        $this->actingAs($admin)
            ->put(route('admin.companies.update', $company), ['seat_limit' => 2])
            ->assertSessionHasErrors('seat_limit');

        $this->assertSame(10, $company->fresh()->seat_limit);
    }

    public function test_only_super_admins_can_manage_admin_permissions(): void
    {
        $superAdmin = $this->makeAdmin();
        $regularAdmin = $this->makeAdmin(Role::ADMIN);

        $this->actingAs($superAdmin)
            ->withoutVite()
            ->get(route('admin.settings.index'))
            ->assertOk();

        $this->actingAs($regularAdmin)
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->post(route('admin.settings.permissions.store'), [
                'admin_user_id' => $regularAdmin->id,
                'feature' => AdminPermission::APPROVE_REQUESTS,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admin_permissions', [
            'admin_user_id' => $regularAdmin->id,
            'feature' => AdminPermission::APPROVE_REQUESTS,
        ]);
    }

    public function test_cancelling_a_trip_releases_company_seats(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);
        $admin = $this->makeAdmin();

        $booking = app(BookingService::class)->book($company, $trip, $user, 3);

        $this->actingAs($admin)
            ->post(route('admin.trips.cancel', $trip))
            ->assertRedirect();

        $this->assertSame('CANCELLED', $trip->fresh()->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(0, $company->fresh()->confirmedSeats());
    }
}

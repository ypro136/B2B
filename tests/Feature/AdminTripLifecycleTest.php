<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class AdminTripLifecycleTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_super_admin_can_complete_a_departed_trip(): void
    {
        $trip = $this->makeTrip([
            'departure_datetime' => now()->subDay(),
        ]);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.trips.complete', $trip))
            ->assertRedirect();

        $this->assertSame(Trip::STATUS_COMPLETED, $trip->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'trip.completed',
            'auditable_id' => $trip->id,
        ]);
    }

    public function test_future_trips_cannot_be_completed(): void
    {
        $trip = $this->makeTrip([
            'departure_datetime' => now()->addDay(),
        ]);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.trips.complete', $trip))
            ->assertSessionHasErrors('trip');

        $this->assertSame(Trip::STATUS_SCHEDULED, $trip->fresh()->status);
    }

    public function test_cancelled_trips_cannot_be_completed(): void
    {
        $trip = $this->makeTrip([
            'departure_datetime' => now()->subDay(),
            'status' => Trip::STATUS_CANCELLED,
        ]);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.trips.complete', $trip))
            ->assertSessionHasErrors('trip');

        $this->assertSame(Trip::STATUS_CANCELLED, $trip->fresh()->status);
    }

    public function test_manage_trips_permission_is_required_to_complete_a_trip(): void
    {
        $trip = $this->makeTrip([
            'departure_datetime' => now()->subDay(),
        ]);

        $this->actingAs($this->makeAdmin(Role::ADMIN))
            ->post(route('admin.trips.complete', $trip))
            ->assertForbidden();

        $this->assertSame(Trip::STATUS_SCHEDULED, $trip->fresh()->status);
    }

    public function test_regular_admin_with_manage_trips_can_complete_a_trip(): void
    {
        $trip = $this->makeTrip([
            'departure_datetime' => now()->subDay(),
        ]);
        $admin = $this->makeAdmin(Role::ADMIN);
        $this->grantPermission($admin, AdminPermission::MANAGE_TRIPS);

        $this->actingAs($admin)
            ->post(route('admin.trips.complete', $trip))
            ->assertRedirect();

        $this->assertSame(Trip::STATUS_COMPLETED, $trip->fresh()->status);
    }

    public function test_complete_action_is_only_shown_for_departed_scheduled_trips(): void
    {
        $this->makeTrip([
            'route_name' => 'Departed Route',
            'departure_datetime' => now()->subDay(),
        ]);

        $this->actingAs($this->makeAdmin())
            ->withoutVite()
            ->get(route('admin.trips.index'))
            ->assertOk()
            ->assertSee('Departed Route')
            ->assertSee('Mark complete');
    }

    public function test_complete_action_is_hidden_for_future_trips(): void
    {
        $this->makeTrip([
            'route_name' => 'Future Route',
            'departure_datetime' => now()->addDay(),
        ]);

        $this->actingAs($this->makeAdmin())
            ->withoutVite()
            ->get(route('admin.trips.index'))
            ->assertOk()
            ->assertSee('Future Route')
            ->assertDontSee('Mark complete');
    }

    private function grantPermission(User $admin, string $feature): void
    {
        AdminPermission::query()->create([
            'admin_user_id' => $admin->id,
            'feature' => $feature,
            'granted_by' => $this->makeAdmin()->id,
            'granted_at' => now(),
        ]);
    }
}

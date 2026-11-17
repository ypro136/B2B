<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Role;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SeatRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_super_admin_can_view_reports(): void
    {
        $this->actingAs($this->makeAdmin())
            ->withoutVite()
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Platform performance')
            ->assertSee('Top companies by seats')
            ->assertSee('Upcoming trips')
            ->assertSee('Recent activity');
    }

    public function test_regular_admin_without_permission_cannot_view_reports(): void
    {
        $this->actingAs($this->makeAdmin(Role::ADMIN))
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }

    public function test_regular_admin_with_view_reports_can_view_reports(): void
    {
        $admin = $this->makeAdmin(Role::ADMIN);
        $this->grantPermission($admin, AdminPermission::VIEW_REPORTS);

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('admin.reports.index'))
            ->assertOk();
    }

    public function test_report_metrics_reflect_confirmed_bookings(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        app(BookingService::class)->book($company, $trip, $user, 4);
        app(SeatRequestService::class)->request($company, $user, 5, 'More capacity');

        $response = $this->actingAs($this->makeAdmin())
            ->withoutVite()
            ->get(route('admin.reports.index'));

        $response->assertOk();

        $response->assertViewHas('summary', function (array $summary) {
            $values = collect($summary)->pluck('value', 'label');

            return $values['Seat utilization'] === '40%'
                && $values['Confirmed bookings'] === 1
                && $values['Pending requests'] === 1;
        });

        $response->assertViewHas('topCompanies', function ($companies) {
            $top = $companies->first();

            return $top['name'] === 'Acme Logistics'
                && $top['seats_used'] === 4
                && $top['utilization'] === 40;
        });

        $response->assertViewHas('upcomingTrips', function ($trips) {
            return $trips->first()['seats_used'] === 4;
        });
    }

    public function test_cancelled_bookings_free_up_reported_seats(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        $bookings = app(BookingService::class);
        $booking = $bookings->book($company, $trip, $user, 4);
        $bookings->cancel($booking, $user);

        $response = $this->actingAs($this->makeAdmin())
            ->withoutVite()
            ->get(route('admin.reports.index'));

        $response->assertViewHas('summary', function (array $summary) {
            $values = collect($summary)->pluck('value', 'label');

            return $values['Seat utilization'] === '0%'
                && $values['Confirmed bookings'] === 0;
        });
    }

    public function test_reports_navigation_is_only_shown_to_authorized_admins(): void
    {
        $superAdmin = $this->makeAdmin();

        $this->actingAs($superAdmin)
            ->withoutVite()
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.reports.index'));

        $this->actingAs($this->makeAdmin(Role::ADMIN))
            ->withoutVite()
            ->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.reports.index'));
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

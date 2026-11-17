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

class AdminAuditLogTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_super_admin_can_browse_the_audit_log(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        app(BookingService::class)->book($company, $trip, $user, 2);

        $this->actingAs($this->makeAdmin())
            ->withoutVite()
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Platform activity')
            ->assertSee('booking.created')
            ->assertSee('Booking #');
    }

    public function test_regular_admin_without_permission_cannot_browse_the_audit_log(): void
    {
        $this->actingAs($this->makeAdmin(Role::ADMIN))
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }

    public function test_view_reports_permission_allows_audit_log_access(): void
    {
        $admin = $this->makeAdmin(Role::ADMIN);
        $this->grantPermission($admin, AdminPermission::VIEW_REPORTS);

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('admin.audit-logs.index'))
            ->assertOk();
    }

    public function test_audit_log_can_be_filtered_by_action(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['total_seats' => 20]);

        app(BookingService::class)->book($company, $trip, $user, 2);
        app(SeatRequestService::class)->request($company, $user, 5, 'More seats');

        $response = $this->actingAs($this->makeAdmin())
            ->withoutVite()
            ->get(route('admin.audit-logs.index', ['action' => 'booking.created']));

        $response->assertOk();

        $response->assertViewHas('logs', function ($logs) {
            return $logs->total() === 1
                && $logs->first()->action === 'booking.created';
        });

        $response->assertViewHas('actions', function ($actions) {
            return $actions->contains('seat_request.created')
                && $actions->contains('booking.created');
        });
    }

    public function test_audit_log_navigation_is_only_shown_to_authorized_admins(): void
    {
        $this->actingAs($this->makeAdmin())
            ->withoutVite()
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.audit-logs.index'));

        $this->actingAs($this->makeAdmin(Role::ADMIN))
            ->withoutVite()
            ->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.audit-logs.index'));
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
